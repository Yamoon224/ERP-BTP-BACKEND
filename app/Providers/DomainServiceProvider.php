<?php

namespace App\Providers;

use App\Domains\Invoicing\Contracts\InvoiceRepositoryContract;
use App\Domains\Invoicing\Repositories\EloquentInvoiceRepository;
use App\Domains\Matching\Contracts\ApprovedOverrideReaderContract;
use App\Domains\Matching\Contracts\ConsumedQuantityReaderContract;
use App\Domains\Matching\Contracts\MatchExceptionRepositoryContract;
use App\Domains\Matching\Contracts\MatchingEngineContract;
use App\Domains\Matching\Contracts\MatchRunRepositoryContract;
use App\Domains\Matching\Contracts\ReceivedQuantityReaderContract;
use App\Domains\Matching\Contracts\TolerancePolicyContract;
use App\Domains\Matching\DTOs\Tolerance;
use App\Domains\Matching\Engines\ThreeWayMatchingEngine;
use App\Domains\Matching\Policies\ConfiguredTolerancePolicy;
use App\Domains\Matching\Repositories\EloquentApprovedOverrideReader;
use App\Domains\Matching\Repositories\EloquentConsumedQuantityReader;
use App\Domains\Matching\Repositories\EloquentMatchExceptionRepository;
use App\Domains\Matching\Repositories\EloquentMatchRunRepository;
use App\Domains\Matching\Services\MatchInputAssembler;
use App\Domains\Payments\Contracts\PaymentAuthorizationRepositoryContract;
use App\Domains\Payments\Contracts\PaymentAuthorizerContract;
use App\Domains\Payments\Repositories\EloquentPaymentAuthorizationRepository;
use App\Domains\Payments\Services\MatchDrivenPaymentAuthorizer;
use App\Domains\Procurement\Contracts\ProjectRepositoryContract;
use App\Domains\Procurement\Contracts\PurchaseOrderRepositoryContract;
use App\Domains\Procurement\Contracts\SupplierRepositoryContract;
use App\Domains\Procurement\Repositories\EloquentProjectRepository;
use App\Domains\Procurement\Repositories\EloquentPurchaseOrderRepository;
use App\Domains\Procurement\Repositories\EloquentSupplierRepository;
use App\Domains\Receiving\Contracts\DeliveryNoteRepositoryContract;
use App\Domains\Receiving\Repositories\EloquentDeliveryNoteRepository;
use App\Domains\Receiving\Repositories\EloquentReceivedQuantityReader;
use App\Domains\Shared\Contracts\ExchangeRateProviderContract;
use App\Domains\Shared\Enums\Currency;
use App\Domains\Shared\Repositories\DatabaseExchangeRateProvider;
use App\Domains\Shared\Services\CurrencyConverter;
use App\Domains\Users\Contracts\UserRepositoryContract;
use App\Domains\Users\Repositories\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Point unique de câblage entre contrats et implémentations (Dependency
 * Inversion).
 *
 * Aucun service métier ne référence une classe concrète de persistance : tout
 * passe par les interfaces listées ici. C'est ce qui permet de substituer une
 * implémentation en test, ou de changer de moteur de rapprochement, en
 * touchant ce seul fichier.
 */
class DomainServiceProvider extends ServiceProvider
{
    /**
     * Contrats de persistance et leurs implémentations Eloquent.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        SupplierRepositoryContract::class => EloquentSupplierRepository::class,
        ProjectRepositoryContract::class => EloquentProjectRepository::class,
        PurchaseOrderRepositoryContract::class => EloquentPurchaseOrderRepository::class,
        DeliveryNoteRepositoryContract::class => EloquentDeliveryNoteRepository::class,
        InvoiceRepositoryContract::class => EloquentInvoiceRepository::class,
        MatchRunRepositoryContract::class => EloquentMatchRunRepository::class,
        MatchExceptionRepositoryContract::class => EloquentMatchExceptionRepository::class,
        PaymentAuthorizationRepositoryContract::class => EloquentPaymentAuthorizationRepository::class,
        UserRepositoryContract::class => EloquentUserRepository::class,

        // Lectures étroites exposées par Receiving et Matching au moteur
        // (Interface Segregation) : le moteur ne voit que ce dont il a besoin.
        ReceivedQuantityReaderContract::class => EloquentReceivedQuantityReader::class,
        ConsumedQuantityReaderContract::class => EloquentConsumedQuantityReader::class,
        ApprovedOverrideReaderContract::class => EloquentApprovedOverrideReader::class,

        PaymentAuthorizerContract::class => MatchDrivenPaymentAuthorizer::class,

        // Resolution des taux de change, historisee en base.
        ExchangeRateProviderContract::class => DatabaseExchangeRateProvider::class,
    ];

    public function register(): void
    {
        // L'assembleur doit connaitre la devise de reference : c'est elle qui
        // sert d'unite d'agregation et de devise des seuils absolus.
        $this->app->bind(MatchInputAssembler::class, fn ($app): MatchInputAssembler => new MatchInputAssembler(
            $app->make(ReceivedQuantityReaderContract::class),
            $app->make(ConsumedQuantityReaderContract::class),
            $app->make(ApprovedOverrideReaderContract::class),
            $app->make(CurrencyConverter::class),
            Currency::from((string) config('matching.base_currency')),
        ));

        // La politique de tolérance est construite depuis la configuration :
        // en changer revient à changer un binding, jamais à toucher au moteur.
        // La tolerance est configuree dans la devise de reference ; la
        // politique la convertira dans la devise de comparaison de chaque
        // facture.
        $this->app->singleton(TolerancePolicyContract::class, fn (): ConfiguredTolerancePolicy => new ConfiguredTolerancePolicy(
            Tolerance::fromArray([
                ...config('matching.tolerance'),
                'currency' => config('matching.base_currency'),
            ]),
        ));

        $this->app->singleton(MatchingEngineContract::class, fn ($app): ThreeWayMatchingEngine => new ThreeWayMatchingEngine(
            $app->make(TolerancePolicyContract::class),
            (string) config('matching.engine_version'),
        ));
    }
}
