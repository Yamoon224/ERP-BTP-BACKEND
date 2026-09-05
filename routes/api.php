<?php

use App\Domains\Auth\Http\Controllers\AuthController;
use App\Domains\Invoicing\Http\Controllers\InvoiceController;
use App\Domains\Matching\Http\Controllers\InvoiceMatchingController;
use App\Domains\Matching\Http\Controllers\MatchExceptionController;
use App\Domains\Matching\Http\Controllers\MatchingDashboardController;
use App\Domains\Payments\Http\Controllers\PaymentAuthorizationController;
use App\Domains\Procurement\Http\Controllers\ProjectController;
use App\Domains\Procurement\Http\Controllers\PurchaseOrderController;
use App\Domains\Procurement\Http\Controllers\SupplierController;
use App\Domains\Receiving\Http\Controllers\DeliveryNoteController;
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
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::patch('/projects/{project}', [ProjectController::class, 'update']);
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
    });

    Route::middleware('permission:invoicing.manage')->group(function (): void {
        Route::post('/invoices', [InvoiceController::class, 'store']);
        Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
    });

    // --- Rapprochement -----------------------------------------------------
    Route::middleware('permission:matching.view')->group(function (): void {
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
