<?php

namespace Tests\Feature\Documentation;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Matching\Enums\ActorType;
use App\Domains\Matching\Enums\DiscrepancySeverity;
use App\Domains\Matching\Enums\DiscrepancyType;
use App\Domains\Matching\Enums\MatchStatus;
use App\Domains\Matching\Enums\ReviewStatus;
use App\Domains\Payments\Enums\PaymentAuthorizationStatus;
use App\Domains\Procurement\Enums\PurchaseOrderStatus;
use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Enums\ExchangeRateSource;
use App\Domains\Shared\Http\Controllers\DocumentationController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garde-fou de la documentation.
 *
 * La spécification OpenAPI est écrite à la main : sans ce test, elle
 * dériverait dès le premier endpoint ajouté ou renommé. Ici, toute route API
 * non documentée — et toute documentation d'une route qui n'existe plus — fait
 * échouer la suite. C'est ce qui permet de tenir la promesse « documentation
 * maintenue en cohérence avec l'API réelle » autrement que par discipline.
 */
class OpenApiSpecificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes API réellement enregistrées, sous la forme utilisée par la
     * spécification (`/invoices/{invoice}` + méthode en minuscules).
     *
     * @return array<string, true>
     */
    private function registeredApiOperations(): array
    {
        $operations = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/')) {
                continue;
            }

            $path = '/'.substr($uri, strlen('api/'));

            foreach ($route->methods() as $method) {
                // HEAD est implicite avec GET, OPTIONS est géré par le
                // framework : ni l'un ni l'autre ne se documente.
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $operations[strtolower($method).' '.$path] = true;
            }
        }

        return $operations;
    }

    /** @return array<string, true> */
    private function documentedOperations(): array
    {
        $specification = DocumentationController::specificationArray();
        $operations = [];

        foreach ($specification['paths'] as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                if ($method === 'parameters') {
                    continue;
                }

                $operations[strtolower($method).' '.$path] = true;
            }
        }

        return $operations;
    }

    #[Test]
    public function the_specification_is_valid_yaml_and_declares_the_expected_structure(): void
    {
        $specification = DocumentationController::specificationArray();

        $this->assertSame('3.0.3', $specification['openapi']);
        $this->assertNotEmpty($specification['info']['title']);
        $this->assertNotEmpty($specification['paths']);
        $this->assertArrayHasKey('bearerAuth', $specification['components']['securitySchemes']);
    }

    #[Test]
    public function every_api_route_is_documented(): void
    {
        $undocumented = array_diff_key($this->registeredApiOperations(), $this->documentedOperations());

        $this->assertSame(
            [],
            array_keys($undocumented),
            'Ces routes API existent mais ne figurent pas dans resources/openapi/openapi.yaml.',
        );
    }

    #[Test]
    public function the_specification_documents_no_route_that_does_not_exist(): void
    {
        $orphans = array_diff_key($this->documentedOperations(), $this->registeredApiOperations());

        $this->assertSame(
            [],
            array_keys($orphans),
            'Ces opérations sont documentées mais ne correspondent à aucune route enregistrée.',
        );
    }

    #[Test]
    public function every_operation_declares_a_summary_and_its_responses(): void
    {
        $specification = DocumentationController::specificationArray();
        $incomplete = [];

        foreach ($specification['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if ($method === 'parameters') {
                    continue;
                }

                if (empty($operation['summary']) || empty($operation['responses'])) {
                    $incomplete[] = strtoupper($method).' '.$path;
                }
            }
        }

        $this->assertSame([], $incomplete, 'Chaque opération doit décrire son objet et ses réponses.');
    }

    #[Test]
    public function every_documented_enum_matches_the_php_enum_it_describes(): void
    {
        // La documentation ne doit pas figer des valeurs que le code a depuis
        // fait évoluer : on compare directement aux enums PHP.
        $schemas = DocumentationController::specificationArray()['components']['schemas'];

        $expectations = [
            'PurchaseOrderStatus' => PurchaseOrderStatus::class,
            'DeliveryNoteStatus' => DeliveryNoteStatus::class,
            'InvoiceStatus' => InvoiceStatus::class,
            'MatchStatus' => MatchStatus::class,
            'DiscrepancyType' => DiscrepancyType::class,
            'DiscrepancySeverity' => DiscrepancySeverity::class,
            'ReviewStatus' => ReviewStatus::class,
            'ActorType' => ActorType::class,
            'PaymentAuthorizationStatus' => PaymentAuthorizationStatus::class,
            'Currency' => Currency::class,
            'ExchangeRateSource' => ExchangeRateSource::class,
        ];

        foreach ($expectations as $schemaName => $enumClass) {
            $this->assertSame(
                $enumClass::values(),
                $schemas[$schemaName]['enum'],
                "Le schéma {$schemaName} ne reflète plus l'enum {$enumClass}.",
            );
        }
    }

    #[Test]
    public function the_specification_is_served_as_json(): void
    {
        $this->getJson('/docs/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonStructure(['info' => ['title', 'version'], 'paths', 'components']);
    }

    #[Test]
    public function the_home_page_links_to_the_documentation(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Documentation API')
            ->assertSee(url('/docs'));
    }

    #[Test]
    public function the_documentation_page_renders_swagger_ui(): void
    {
        $this->get('/docs')
            ->assertOk()
            ->assertSee('swagger-ui', escape: false)
            ->assertSee(url('/docs/openapi.json'), escape: false);
    }
}
