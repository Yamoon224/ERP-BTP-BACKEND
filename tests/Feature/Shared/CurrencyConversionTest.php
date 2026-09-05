<?php

namespace Tests\Feature\Shared;

use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Enums\ExchangeRateSource;
use App\Domains\Shared\Exceptions\ExchangeRateUnavailableException;
use App\Domains\Shared\Services\CurrencyConverter;
use App\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Résolution des taux et conversion, contre la base réelle.
 *
 * Seul le sens EUR → X est enregistré : l'inverse et la triangulation sont
 * calculés. Ces tests vérifient que ce choix tient, y compris pour la paire
 * USD → XOF qui n'est jamais cotée en direct.
 */
class CurrencyConversionTest extends TestCase
{
    use RefreshDatabase;

    private const XOF_PEG = 655.957;

    protected function setUp(): void
    {
        parent::setUp();

        ExchangeRate::create([
            'base_currency' => Currency::EUR,
            'quote_currency' => Currency::XOF,
            'rate' => self::XOF_PEG,
            'source' => ExchangeRateSource::FixedPeg,
            'effective_from' => '1999-01-01',
        ]);

        ExchangeRate::create([
            'base_currency' => Currency::EUR,
            'quote_currency' => Currency::USD,
            'rate' => 1.0850,
            'source' => ExchangeRateSource::Manual,
            'effective_from' => '2026-01-01',
        ]);
    }

    private function converter(): CurrencyConverter
    {
        return app(CurrencyConverter::class);
    }

    #[Test]
    public function converting_a_currency_into_itself_leaves_the_amount_untouched(): void
    {
        $result = $this->converter()->convert(1234.56, Currency::EUR, Currency::EUR);

        $this->assertSame(1234.56, $result->amount);
        $this->assertTrue($result->isUnchanged());
    }

    #[Test]
    public function it_applies_a_direct_rate(): void
    {
        $result = $this->converter()->convert(100.0, Currency::EUR, Currency::USD, '2026-06-01');

        $this->assertSame(108.5, $result->amount);
        $this->assertSame(1.085, $result->rate->rate);
    }

    #[Test]
    public function it_derives_the_inverse_rate_rather_than_storing_it(): void
    {
        // 108,50 USD doivent redonner 100 EUR sans qu'aucune ligne USD → EUR
        // n'existe en base : deux lignes à maintenir finiraient par diverger.
        $result = $this->converter()->convert(108.50, Currency::USD, Currency::EUR, '2026-06-01');

        $this->assertSame(100.0, $result->amount);
    }

    #[Test]
    public function it_triangulates_a_pair_that_is_never_quoted_directly(): void
    {
        // USD → XOF passe par l'euro : 1 085 USD = 1 000 EUR = 655 957 XOF.
        $result = $this->converter()->convert(1085.0, Currency::USD, Currency::XOF, '2026-06-01');

        $this->assertEqualsWithDelta(655957.0, $result->amount, 1.0);
    }

    #[Test]
    public function a_triangulated_rate_is_never_stronger_than_its_weakest_link(): void
    {
        // EUR → XOF est une parité fixe, EUR → USD une simple saisie : le
        // résultat combiné ne peut pas se présenter comme une parité fixe.
        $rate = $this->converter()->rateFor(Currency::USD, Currency::XOF, '2026-06-01');

        $this->assertSame(ExchangeRateSource::Manual, $rate->source);
    }

    #[Test]
    public function a_cfa_franc_amount_never_carries_decimals(): void
    {
        // Le franc CFA n'a pas de sous-unité : produire des centimes créerait
        // un écart systématique avec le montant réellement virable.
        $result = $this->converter()->convert(123.456, Currency::EUR, Currency::XOF, '2026-06-01');

        $this->assertSame(0.0, fmod($result->amount, 1.0));
        $this->assertSame(Currency::XOF, $result->currency);
    }

    #[Test]
    public function a_unit_price_is_converted_without_being_rounded(): void
    {
        // Un prix unitaire sera multiplié par une quantité : l'arrondir avant
        // décalerait le total de la ligne.
        $amount = $this->converter()->convert(1.0, Currency::EUR, Currency::XOF, '2026-06-01')->amount;
        $unitPrice = $this->converter()->convertUnitPrice(1.0, Currency::EUR, Currency::XOF, '2026-06-01')->amount;

        $this->assertSame(656.0, $amount, 'Un montant est arrondi au franc.');
        $this->assertSame(self::XOF_PEG, $unitPrice, 'Un prix unitaire garde sa précision.');
    }

    #[Test]
    public function it_uses_the_rate_in_force_on_the_requested_date(): void
    {
        ExchangeRate::create([
            'base_currency' => Currency::EUR,
            'quote_currency' => Currency::USD,
            'rate' => 1.2000,
            'source' => ExchangeRateSource::Manual,
            'effective_from' => '2026-07-01',
        ]);

        // Une facture de juin doit être convertie au taux de juin, pas au taux
        // d'aujourd'hui : c'est ce qui rend un rapprochement rejouable.
        $june = $this->converter()->convert(100.0, Currency::EUR, Currency::USD, '2026-06-15');
        $august = $this->converter()->convert(100.0, Currency::EUR, Currency::USD, '2026-08-15');

        $this->assertSame(108.5, $june->amount);
        $this->assertSame(120.0, $august->amount);
    }

    #[Test]
    public function it_ignores_a_rate_that_is_not_yet_in_force(): void
    {
        // Le taux EUR → USD n'entre en vigueur qu'au 2026-01-01 : pour une
        // date anterieure, il n'est pas applicable et le convertisseur refuse
        // plutot que d'anticiper un taux futur sur une facture passee.
        $this->assertFalse($this->converter()->hasRateFor(Currency::EUR, Currency::USD, '2025-12-31'));

        $this->expectException(ExchangeRateUnavailableException::class);
        $this->converter()->convert(100.0, Currency::EUR, Currency::USD, '2025-12-31');
    }

    #[Test]
    public function it_refuses_to_guess_when_no_rate_exists(): void
    {
        ExchangeRate::query()->delete();

        $this->expectException(ExchangeRateUnavailableException::class);

        $this->converter()->rateFor(Currency::EUR, Currency::USD);
    }

    #[Test]
    public function the_fixed_peg_applies_to_any_date_since_it_never_moves(): void
    {
        $old = $this->converter()->convert(1.0, Currency::EUR, Currency::XOF, '2005-03-01');
        $recent = $this->converter()->convert(1.0, Currency::EUR, Currency::XOF, '2026-09-01');

        $this->assertSame($old->rate->rate, $recent->rate->rate);
        $this->assertSame(ExchangeRateSource::FixedPeg, $recent->rate->source);
    }
}
