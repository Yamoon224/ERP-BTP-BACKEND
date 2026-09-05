<?php

namespace App\Domains\Receiving\Http\Controllers;

use App\Domains\Receiving\Http\Requests\ReviewDeliveryNoteRequest;
use App\Domains\Receiving\Http\Requests\StoreDeliveryNoteRequest;
use App\Domains\Receiving\Http\Resources\DeliveryNoteResource;
use App\Domains\Receiving\Services\DeliveryNoteService;
use App\Http\Controllers\Controller;
use App\Models\DeliveryNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeliveryNoteController extends Controller
{
    public function __construct(private readonly DeliveryNoteService $deliveryNoteService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return DeliveryNoteResource::collection($this->deliveryNoteService->list(
            $request->only('search', 'purchase_order_id', 'supplier_id', 'status', 'sort', 'direction'),
            $request->integer('per_page', 10),
        ));
    }

    public function store(StoreDeliveryNoteRequest $request): JsonResponse
    {
        $deliveryNote = $this->deliveryNoteService->record($request->validated(), $request->user());

        return (new DeliveryNoteResource($deliveryNote))->response()->setStatusCode(201);
    }

    public function show(DeliveryNote $deliveryNote): DeliveryNoteResource
    {
        return new DeliveryNoteResource($this->deliveryNoteService->find($deliveryNote->id));
    }

    /**
     * Controle de reception : accepter ou refuser la marchandise. Accepter
     * rend les quantites opposables au paiement et relance le rapprochement
     * des factures du meme bon de commande.
     */
    public function review(ReviewDeliveryNoteRequest $request, DeliveryNote $deliveryNote): DeliveryNoteResource
    {
        return new DeliveryNoteResource($this->deliveryNoteService->review(
            $deliveryNote,
            $request->decision(),
            $request->user(),
        ));
    }
}
