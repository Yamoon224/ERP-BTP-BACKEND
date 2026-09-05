<?php

namespace Tests\Feature\Invoicing;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Matching\Services\InvoiceMatchingService;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Enums\ExchangeRateSource;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\MatchRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\BuildsProcurementScenario;
use Tests\TestCase;

/**
 * Export PDF d'une facture et changement de sa devise de reglement.
 *
 * Les deux fonctions se rejoignent sur un point : la devise n'est pas un
 * detail d'affichage. C'est l'unite dans laquelle le prix facture est
 * confronte au prix commande, donc celle du montant autorise au paiement — le
 * PDF doit la porter, et la changer doit rejouer le controle.
 */
class InvoiceExportAndCurrencyTest extends TestCase
{
    use BuildsProcurementScenario, RefreshDatabase;

    private function givenPeg(): void
    {
        ExchangeRate::create([
            'base_currency' => Currency::EUR,
            'quote_currency' => Currency::XOF,
            'rate' => 655.957,
            'source' => ExchangeRateSource::FixedPeg,
            'effective_from' => '1999-01-01',
        ]);
    }

    private function givenMatchedInvoice(): Invoice
    {
        $this->givenPurchaseOrder([['quantity' => 10, 'price' => 100]]);
        $this->givenAcceptedDelivery([10]);

        $this->postJson(
            '/api/invoices',
            $this->invoicePayload([['line' => 0, 'quantity' => 10, 'price' => 100]]),
        )->assertCreated();

        return Invoice::firstOrFail();
    }

    #[Test]
    public function it_exports_an_invoice_as_a_pdf_named_after_its_reference(): void
    {
        $this->actingAsRole('accountant');
        $invoice = $this->givenMatchedInvoice();

        $response = $this->get("/api/invoices/{$invoice->id}/pdf")->assertOk();

        $response->assertHeader('content-type', 'application/pdf');
        // Le nom porte la reference : un « telechargement.pdf » dans un dossier
        // de comptabilite ne se retrouve pas.
        $this->assertStringContainsString(
            'facture-FAC-TEST-1.pdf',
            (string) $response->headers->get('content-disposition'),
        );

        // %PDF- : le corps est bien un document, pas une page d'erreur rendue.
        $this->assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    #[Test]
    public function exporting_requires_only_the_right_to_read_the_invoice(): void
    {
        $this->actingAsRole('accountant');
        $invoice = $this->givenMatchedInvoice();

        // Le magasinier ne voit pas les factures : il ne les exporte pas non plus.
        $this->actingAsRole('warehouse');
        $this->get("/api/invoices/{$invoice->id}/pdf")->assertForbidden();
    }

    #[Test]
    public function changing_the_currency_replays_the_match_and_archives_a_new_run(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPeg();
        $invoice = $this->givenMatchedInvoice();

        $runsBefore = MatchRun::where('invoice_id', $invoice->id)->count();

        $this->patchJson("/api/invoices/{$invoice->id}/currency", ['currency' => 'XOF'])
            ->assertOk()
            ->assertJsonPath('data.currency', 'XOF');

        $runs = MatchRun::where('invoice_id', $invoice->id)->orderBy('id')->get();

        // Une execution de plus, pas une execution modifiee : la precedente
        // reste consultable telle qu'elle a ete decidee.
        $this->assertCount($runsBefore + 1, $runs);
        $this->assertSame(
            InvoiceMatchingService::TRIGGER_CURRENCY_CHANGED,
            $runs->last()->trigger,
        );
        $this->assertSame(Currency::EUR, $runs->first()->currency);
        $this->assertSame(Currency::XOF, $runs->last()->currency);
    }

    #[Test]
    public function changing_the_currency_does_not_convert_the_invoiced_amounts(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPeg();
        $invoice = $this->givenMatchedInvoice();

        $this->patchJson("/api/invoices/{$invoice->id}/currency", ['currency' => 'XOF'])
            ->assertOk()
            // Corriger la devise corrige la facon dont la facture a ete lue,
            // pas ce que le fournisseur a ecrit dessus : 1 000 reste 1 000.
            ->assertJsonPath('data.total_amount', 1000);
    }

    #[Test]
    public function an_unknown_currency_is_rejected(): void
    {
        $this->actingAsRole('accountant');
        $invoice = $this->givenMatchedInvoice();

        $this->patchJson("/api/invoices/{$invoice->id}/currency", ['currency' => 'GBP'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('currency');
    }

    #[Test]
    public function a_cancelled_invoice_keeps_its_currency(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPeg();
        $invoice = $this->givenMatchedInvoice();

        $this->postJson("/api/invoices/{$invoice->id}/cancel")->assertOk();

        $this->patchJson("/api/invoices/{$invoice->id}/currency", ['currency' => 'XOF'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'invoice_currency_not_changeable');

        $this->assertSame(InvoiceStatus::Cancelled, $invoice->refresh()->status);
        $this->assertSame(Currency::EUR, $invoice->currency);
    }

    #[Test]
    public function a_settled_invoice_keeps_its_currency(): void
    {
        $this->actingAsRole('accountant');
        $this->givenPeg();
        $invoice = $this->givenMatchedInvoice();

        $authorization = $invoice->paymentAuthorizations()->firstOrFail();

        $this->postJson("/api/payment-authorizations/{$authorization->id}/settle", [
            'payment_reference' => 'VIR-2026-00001',
            'payment_method' => 'transfer',
        ])->assertOk();

        // Le virement est parti : changer la devise apres coup reecrirait le
        // sens d'un paiement deja execute.
        $this->patchJson("/api/invoices/{$invoice->id}/currency", ['currency' => 'XOF'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'invoice_currency_not_changeable');
    }

    #[Test]
    public function reading_an_invoice_does_not_allow_changing_its_currency(): void
    {
        $this->actingAsRole('accountant');
        $invoice = $this->givenMatchedInvoice();

        // Le controleur financier arbitre les ecarts ; il ne redefinit pas la
        // devise de la creance.
        $this->actingAsRole('controller');
        $this->patchJson("/api/invoices/{$invoice->id}/currency", ['currency' => 'XOF'])
            ->assertForbidden();
    }
}
