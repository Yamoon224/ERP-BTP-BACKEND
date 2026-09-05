<?php

namespace Tests\Feature\Shared;

use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Enums\ExchangeRateSource;
use App\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Administration du referentiel de taux.
 *
 * Deux garanties sont verifiees ici, et elles ne sont pas cosmetiques :
 *
 *  - une parite fixe reglementaire ne se retouche pas depuis un ecran
 *    d'administration. Le franc CFA vaut 1/655,957 euro par un texte, pas par
 *    une saisie ; laisser un utilisateur la reecrire fausserait silencieusement
 *    tous les rapprochements passes qui s'y referent ;
 *  - consulter un taux et en saisir un sont deux permissions distinctes, parce
 *    qu'un taux manuel deplace directement le montant autorise au paiement.
 */
class ExchangeRateApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'base_currency' => Currency::EUR->value,
            'quote_currency' => Currency::USD->value,
            'rate' => 1.0912,
            'source' => ExchangeRateSource::Manual->value,
            'effective_from' => now()->toDateString(),
        ], $overrides);
    }

    #[Test]
    public function it_serves_the_currency_reference_with_real_decimal_counts(): void
    {
        $this->actingAsRole('accountant');

        /** @var list<array{code: string, decimals: int}> $definitions */
        $definitions = $this->getJson('/api/currencies')->assertOk()->json('data.currencies');
        $decimals = array_column($definitions, 'decimals', 'code');

        // Le franc CFA n'a pas de sous-unite. Si l'interface arrondissait a deux
        // decimales quand le backend arrondit a zero, les deux afficheraient un
        // montant different pour la meme autorisation de paiement — d'ou le fait
        // de servir cette valeur plutot que de la recopier cote client.
        $this->assertSame(0, $decimals['XOF']);
        $this->assertSame(2, $decimals['EUR']);
        $this->assertSame(2, $decimals['USD']);
    }

    #[Test]
    public function it_publishes_the_configured_default_and_base_currencies(): void
    {
        // phpunit.xml fige l'euro pour que les tests du moteur ne dependent pas
        // du .env local ; la configuration livree, elle, regle en franc CFA. On
        // pose donc explicitement les valeurs pour verifier le cablage, pas la
        // valeur du jour.
        config(['matching.default_currency' => 'XOF', 'matching.base_currency' => 'XOF']);
        $this->actingAsRole('accountant');

        $this->getJson('/api/currencies')
            ->assertOk()
            ->assertJsonPath('data.default_currency', 'XOF')
            ->assertJsonPath('data.base_currency', 'XOF');
    }

    #[Test]
    public function an_accountant_records_a_quotation(): void
    {
        $this->actingAsRole('accountant');

        $this->postJson('/api/exchange-rates', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.base_currency', 'EUR')
            ->assertJsonPath('data.quote_currency', 'USD')
            ->assertJsonPath('data.rate', 1.0912)
            ->assertJsonPath('data.is_editable', true);
    }

    #[Test]
    public function it_refuses_a_currency_quoted_against_itself(): void
    {
        $this->actingAsRole('accountant');

        $this->postJson('/api/exchange-rates', $this->payload(['quote_currency' => 'EUR']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('quote_currency');
    }

    #[Test]
    public function a_fixed_peg_cannot_be_recorded_by_hand(): void
    {
        $this->actingAsRole('accountant');

        $this->postJson('/api/exchange-rates', $this->payload(['source' => 'fixed_peg']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('source');
    }

    #[Test]
    public function a_fixed_peg_can_be_neither_updated_nor_deleted(): void
    {
        $this->actingAsRole('accountant');

        $peg = ExchangeRate::factory()->create([
            'base_currency' => Currency::EUR,
            'quote_currency' => Currency::XOF,
            'rate' => 655.957,
            'source' => ExchangeRateSource::FixedPeg,
        ]);

        $this->patchJson("/api/exchange-rates/{$peg->id}", ['rate' => 700])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'fixed_peg_not_editable');

        $this->deleteJson("/api/exchange-rates/{$peg->id}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'fixed_peg_not_editable');

        $this->assertDatabaseHas('exchange_rates', ['id' => $peg->id, 'rate' => 655.9570000000]);
    }

    #[Test]
    public function a_manual_quotation_can_be_corrected_and_removed(): void
    {
        $this->actingAsRole('accountant');

        $rate = ExchangeRate::factory()->create([
            'base_currency' => Currency::EUR,
            'quote_currency' => Currency::USD,
            'source' => ExchangeRateSource::Manual,
        ]);

        $this->patchJson("/api/exchange-rates/{$rate->id}", ['rate' => 1.1])
            ->assertOk()
            ->assertJsonPath('data.rate', 1.1);

        $this->deleteJson("/api/exchange-rates/{$rate->id}")->assertNoContent();

        $this->assertDatabaseMissing('exchange_rates', ['id' => $rate->id]);
    }

    #[Test]
    public function reading_a_rate_and_writing_one_are_two_different_permissions(): void
    {
        // Le controleur financier consulte les taux pour arbitrer un ecart de
        // prix ; il ne les fixe pas.
        $this->actingAsRole('controller');

        $this->getJson('/api/exchange-rates')->assertOk();
        $this->postJson('/api/exchange-rates', $this->payload())->assertForbidden();
    }

    #[Test]
    public function a_warehouse_operator_sees_no_rate_at_all(): void
    {
        $this->actingAsRole('warehouse');

        $this->getJson('/api/exchange-rates')->assertForbidden();
        $this->getJson('/api/currencies')->assertForbidden();
    }
}
