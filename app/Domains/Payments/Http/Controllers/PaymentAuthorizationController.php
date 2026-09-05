<?php

namespace App\Domains\Payments\Http\Controllers;

use App\Domains\Payments\Contracts\PaymentAuthorizationRepositoryContract;
use App\Domains\Payments\Http\Requests\SettlePaymentRequest;
use App\Domains\Payments\Http\Resources\PaymentAuthorizationResource;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Autorisations de paiement.
 *
 * Une autorisation ne nait jamais d un appel direct : elle est le produit d un
 * rapprochement. La seule ecriture ouverte ici est le **reglement** — constater
 * qu un montant deja autorise a effectivement ete paye — et il n accepte aucun
 * montant en entree, precisement pour qu il ne devienne pas une porte derobee
 * vers un paiement non controle.
 */
class PaymentAuthorizationController extends Controller
{
    public function __construct(
        private readonly PaymentAuthorizationRepositoryContract $authorizations,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return PaymentAuthorizationResource::collection($this->authorizations->paginate(
            $request->only('invoice_id', 'supplier_id', 'status', 'settled', 'search', 'sort', 'direction'),
            $request->integer('per_page', 10),
        ));
    }

    /** Autorisation active d une facture, ou null si rien n est payable. */
    public function forInvoice(Invoice $invoice): JsonResponse
    {
        $authorization = $this->authorizations->activeForInvoice($invoice->id);

        return response()->json([
            'data' => $authorization === null
                ? null
                : new PaymentAuthorizationResource($authorization->load(['invoice.supplier', 'settler'])),
        ]);
    }

    /** Constate le reglement effectif d une autorisation active. */
    public function settle(
        SettlePaymentRequest $request,
        PaymentAuthorization $paymentAuthorization,
    ): PaymentAuthorizationResource {
        return new PaymentAuthorizationResource($this->authorizations->settle(
            $paymentAuthorization,
            $request->validated(),
            $request->user(),
        ));
    }
}
