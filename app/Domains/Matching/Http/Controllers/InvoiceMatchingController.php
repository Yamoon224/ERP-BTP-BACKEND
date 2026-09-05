<?php

namespace App\Domains\Matching\Http\Controllers;

use App\Domains\Matching\Contracts\MatchRunRepositoryContract;
use App\Domains\Matching\Http\Resources\MatchRunResource;
use App\Domains\Matching\Services\InvoiceMatchingService;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Rapprochement d une facture et consultation de sa piste d audit.
 */
class InvoiceMatchingController extends Controller
{
    public function __construct(
        private readonly InvoiceMatchingService $matchingService,
        private readonly MatchRunRepositoryContract $matchRuns,
    ) {}

    /** Historique complet des rapprochements d une facture, du plus recent au plus ancien. */
    public function index(Request $request, Invoice $invoice): AnonymousResourceCollection
    {
        return MatchRunResource::collection($this->matchRuns->paginateForInvoice(
            $invoice->id,
            $request->only('status'),
            $request->integer('per_page', 10),
        ));
    }

    /**
     * Rejoue le rapprochement. L utilisateur qui declenche est enregistre
     * comme auteur de la decision resultante.
     */
    public function store(Request $request, Invoice $invoice): JsonResponse
    {
        $matchRun = $this->matchingService->match(
            $invoice,
            $request->user(),
            InvoiceMatchingService::TRIGGER_MANUAL,
        );

        return (new MatchRunResource($matchRun))->response()->setStatusCode(201);
    }

    public function show(Invoice $invoice, string $matchRun): MatchRunResource
    {
        $run = $this->matchRuns->findOrFail($matchRun);

        // Une execution appartient a une facture et une seule : la servir sous
        // une autre facture reviendrait a exposer une piste d audit hors de son
        // contexte.
        abort_if($run->invoice_id !== $invoice->id, 404);

        return new MatchRunResource($run);
    }
}
