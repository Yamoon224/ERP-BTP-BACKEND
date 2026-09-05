<?php

namespace App\Domains\Invoicing\Http\Controllers;

use App\Domains\Invoicing\Http\Requests\ChangeInvoiceCurrencyRequest;
use App\Domains\Invoicing\Http\Requests\StoreInvoiceRequest;
use App\Domains\Invoicing\Http\Resources\InvoiceResource;
use App\Domains\Invoicing\Services\InvoicePdfGenerator;
use App\Domains\Invoicing\Services\InvoiceService;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly InvoicePdfGenerator $pdfGenerator,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return InvoiceResource::collection($this->invoiceService->list(
            $request->only('search', 'supplier_id', 'purchase_order_id', 'status', 'currency', 'sort', 'direction'),
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

    /**
     * Change la devise de reglement, puis rejoue le rapprochement.
     *
     * La reponse porte la facture rechargee avec sa nouvelle execution : le
     * client n'a pas a redemander le verdict, qui a forcement change.
     */
    public function changeCurrency(ChangeInvoiceCurrencyRequest $request, Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($this->invoiceService->changeCurrency(
            $invoice,
            (string) $request->validated('currency'),
            $request->user(),
        ));
    }

    public function cancel(Invoice $invoice): InvoiceResource
    {
        $this->invoiceService->cancel($invoice);

        return new InvoiceResource($this->invoiceService->find($invoice->id));
    }

    /**
     * Export PDF de la facture, verdict de rapprochement compris.
     *
     * Servi en flux plutot qu'ecrit sur disque : le document est integralement
     * derive de l'etat courant, et le stocker ferait exister deux verites dont
     * l'une vieillirait des le prochain rapprochement.
     */
    public function pdf(Request $request, Invoice $invoice): HttpResponse
    {
        $loaded = $this->invoiceService->find($invoice->id);
        $document = $this->pdfGenerator->generate($loaded, $request->user());

        return $document->download($this->pdfGenerator->filenameFor($loaded));
    }
}
