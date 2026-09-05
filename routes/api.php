<?php

use App\Domains\Audit\Http\Controllers\AuditLogController;
use App\Domains\Auth\Http\Controllers\AuthController;
use App\Domains\Invoicing\Http\Controllers\InvoiceController;
use App\Domains\Matching\Http\Controllers\InvoiceMatchingController;
use App\Domains\Matching\Http\Controllers\MatchExceptionController;
use App\Domains\Matching\Http\Controllers\MatchingDashboardController;
use App\Domains\Matching\Http\Controllers\MatchRunController;
use App\Domains\Payments\Http\Controllers\PaymentAuthorizationController;
use App\Domains\Procurement\Http\Controllers\ProjectController;
use App\Domains\Procurement\Http\Controllers\PurchaseOrderController;
use App\Domains\Procurement\Http\Controllers\SupplierController;
use App\Domains\Receiving\Http\Controllers\DeliveryNoteController;
use App\Domains\Shared\Http\Controllers\CurrencyController;
use App\Domains\Shared\Http\Controllers\ExchangeRateController;
use App\Domains\Shared\Http\Controllers\HealthController;
use App\Domains\Users\Http\Controllers\ProfileController;
use App\Domains\Users\Http\Controllers\RoleController;
use App\Domains\Users\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API REST
|--------------------------------------------------------------------------
|
| Conventions : ressources au pluriel, verbes HTTP porteurs de l'intention,
| 201 sur création, 204 sur suppression sans corps, 422 sur validation, 409 sur
| conflit d'état métier. Les actions qui ne sont pas un CRUD (arbitrer un
| écart, contrôler une livraison) sont exposées comme des sous-ressources
| POST plutôt que comme des verbes inventés.
|
| Autorisation : Sanctum en mode token (le frontend NextJS est sur une autre
| origine, sans session partagée), doublé de permissions granulaires par
| domaine — voir RolesAndPermissionsSeeder.
|
*/

Route::get('/health', HealthController::class);

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // --- Compte de l'utilisateur connecte ----------------------------------
    // Aucune permission : ces routes n'agissent que sur l'appelant lui-meme,
    // et la garantie tient a cela, pas a un controle d'identifiant.
    Route::patch('/me', [ProfileController::class, 'update']);
    Route::put('/me/password', [ProfileController::class, 'updatePassword'])->middleware('throttle:6,1');

    // --- Administration des comptes ----------------------------------------
    Route::middleware('permission:users.view')->group(function (): void {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::get('/roles', RoleController::class);
    });

    Route::middleware('permission:users.manage')->group(function (): void {
        Route::post('/users', [UserController::class, 'store']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
    });

    // --- Référentiel achats ------------------------------------------------
    Route::middleware('permission:procurement.view')->group(function (): void {
        Route::get('/suppliers', [SupplierController::class, 'index']);
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
        Route::get('/projects', [ProjectController::class, 'index']);
        Route::get('/projects/{project}', [ProjectController::class, 'show']);
        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
    });

    Route::middleware('permission:procurement.manage')->group(function (): void {
        Route::post('/suppliers', [SupplierController::class, 'store']);
        Route::patch('/suppliers/{supplier}', [SupplierController::class, 'update']);
        // Suppression refusee (409) des qu'un document cite la fiche : le
        // referentiel est ce qui rend une decision archivee relisible.
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy']);
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::patch('/projects/{project}', [ProjectController::class, 'update']);
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
    });

    // --- Réceptions --------------------------------------------------------
    Route::middleware('permission:receiving.view')->group(function (): void {
        Route::get('/delivery-notes', [DeliveryNoteController::class, 'index']);
        Route::get('/delivery-notes/{deliveryNote}', [DeliveryNoteController::class, 'show']);
    });

    Route::middleware('permission:receiving.manage')->group(function (): void {
        Route::post('/delivery-notes', [DeliveryNoteController::class, 'store']);
        // Contrôle de réception : accepter/refuser. Séparé de la création
        // parce que saisir une livraison et la valider sont deux
        // responsabilités qu'on veut pouvoir confier à deux personnes.
        Route::post('/delivery-notes/{deliveryNote}/review', [DeliveryNoteController::class, 'review']);
    });

    // --- Factures ----------------------------------------------------------
    Route::middleware('permission:invoicing.view')->group(function (): void {
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        // Export PDF : une lecture, servie a qui peut deja lire la facture.
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
    });

    Route::middleware('permission:invoicing.manage')->group(function (): void {
        Route::post('/invoices', [InvoiceController::class, 'store']);
        // Changer la devise de reglement rejoue le rapprochement : le montant
        // autorise ne se lit plus dans la meme unite, il doit etre recalcule.
        Route::patch('/invoices/{invoice}/currency', [InvoiceController::class, 'changeCurrency']);
        Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
    });

    // --- Rapprochement -----------------------------------------------------
    Route::middleware('permission:matching.view')->group(function (): void {
        // Registre global des executions, toutes factures confondues. Aucune
        // route d'ecriture : une execution est immuable, on en cree une
        // nouvelle en rejouant le rapprochement de la facture concernee.
        Route::get('/match-runs', [MatchRunController::class, 'index']);
        Route::get('/match-runs/{matchRun}', [MatchRunController::class, 'show']);
        Route::get('/invoices/{invoice}/match-runs', [InvoiceMatchingController::class, 'index']);
        Route::get('/invoices/{invoice}/match-runs/{matchRun}', [InvoiceMatchingController::class, 'show']);
        Route::get('/match-exceptions', [MatchExceptionController::class, 'index']);
        Route::get('/match-exceptions/{matchException}', [MatchExceptionController::class, 'show']);
        Route::get('/dashboard/matching', MatchingDashboardController::class);
    });

    Route::middleware('permission:matching.run')->group(function (): void {
        Route::post('/invoices/{invoice}/match-runs', [InvoiceMatchingController::class, 'store']);
    });

    // L'arbitrage d'un écart est la permission la plus sensible du système :
    // c'est le seul geste humain capable de débloquer un paiement.
    Route::middleware('permission:matching.review')->group(function (): void {
        Route::post('/match-exceptions/{matchException}/review', [MatchExceptionController::class, 'review']);
    });

    // --- Devises et taux de change -----------------------------------------
    Route::middleware('permission:currencies.view')->group(function (): void {
        Route::get('/currencies', CurrencyController::class);
        Route::get('/exchange-rates', [ExchangeRateController::class, 'index']);
        Route::get('/exchange-rates/{exchangeRate}', [ExchangeRateController::class, 'show']);
    });

    // Saisir un taux deplace le montant autorise au paiement : la permission
    // est distincte de la simple consultation, et la parite fixe EUR/XOF reste
    // hors d'atteinte quelle que soit la permission.
    Route::middleware('permission:currencies.manage')->group(function (): void {
        Route::post('/exchange-rates', [ExchangeRateController::class, 'store']);
        Route::patch('/exchange-rates/{exchangeRate}', [ExchangeRateController::class, 'update']);
        Route::delete('/exchange-rates/{exchangeRate}', [ExchangeRateController::class, 'destroy']);
    });

    // --- Journal d'audit ----------------------------------------------------
    // Lecture seule, sans exception : ni creation, ni modification, ni purge
    // par l'API. Un journal que l'on peut editer n'atteste de rien.
    Route::middleware('permission:audit.view')->group(function (): void {
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/facets', [AuditLogController::class, 'facets']);
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show']);
    });

    // --- Paiements ---------------------------------------------------------
    Route::middleware('permission:payments.view')->group(function (): void {
        Route::get('/payment-authorizations', [PaymentAuthorizationController::class, 'index']);
        Route::get('/invoices/{invoice}/payment-authorization', [PaymentAuthorizationController::class, 'forInvoice']);
    });

    // Le reglement constate un paiement deja autorise : il n'accepte aucun
    // montant, et ne peut donc pas servir a payer ce que le moteur a bloque.
    Route::middleware('permission:payments.manage')->group(function (): void {
        Route::post('/payment-authorizations/{paymentAuthorization}/settle', [PaymentAuthorizationController::class, 'settle']);
    });
});
