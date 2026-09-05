<?php

namespace App\Domains\Invoicing\Http\Controllers;

use App\Domains\Invoicing\Http\Requests\StoreInvoiceRequest;
use App\Domains\Invoicing\Http\Resources\InvoiceResource;
use App\Domains\Invoicing\Services\InvoiceService;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection($this->invoiceService->list(
            $request->only('search', 'supplier_id', 'purchase_order_id', 'status', 'sort', 'direction'),
            $request->integer('per_page', 10),
        ));
    }

    /**
     * Soumettre une facture declenche immediatement son rapprochement : la
     * reponse porte deja le verdict et, le cas echeant, le montant autorise.
     */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->submit($request->validated(), $request->user());

        return (new InvoiceResource($invoice))->response()->setStatusCode(201);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoiceService->find($invoice->id));
    }

    public function cancel(Invoice $invoice): InvoiceResource
    {
        $this->invoiceService->cancel($invoice);

        return new InvoiceResource($this->invoiceService->find($invoice->id));
    }
}
