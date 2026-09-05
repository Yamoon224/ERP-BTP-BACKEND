<?php

namespace App\Domains\Matching\Http\Controllers;

use App\Domains\Matching\Contracts\MatchExceptionRepositoryContract;
use App\Domains\Matching\Http\Requests\ReviewMatchExceptionRequest;
use App\Domains\Matching\Http\Resources\MatchExceptionResource;
use App\Domains\Matching\Http\Resources\MatchRunResource;
use App\Domains\Matching\Services\MatchExceptionReviewService;
use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\MatchException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * File de revue humaine des ecarts (regle fonctionnelle n6).
 */
class MatchExceptionController extends Controller
{
    use ApiResponses;

    public function __construct(
        private readonly MatchExceptionRepositoryContract $exceptions,
        private readonly MatchExceptionReviewService $reviewService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return MatchExceptionResource::collection($this->exceptions->paginate(
            $request->only('review_status', 'type', 'severity', 'invoice_id', 'supplier_id', 'sort', 'direction'),
            $request->integer('per_page', 10),
        ));
    }

    public function show(MatchException $matchException): MatchExceptionResource
    {
        return new MatchExceptionResource($this->exceptions->findOrFail($matchException->id));
    }

    /**
     * Arbitrage. Un accord relance le rapprochement (le moteur seul decide du
     * montant payable) ; un refus met la facture en litige et revoque
     * l autorisation en cours.
     */
    public function review(ReviewMatchExceptionRequest $request, MatchException $matchException): JsonResponse
    {
        $result = $this->reviewService->review(
            $matchException,
            $request->decision(),
            $request->user(),
            $request->input('note'),
        );

        return $this->ok([
            'exception' => new MatchExceptionResource($result['exception']->load(['reviewer', 'invoice.supplier'])),
            'match_run' => $result['match_run'] === null ? null : new MatchRunResource($result['match_run']),
        ]);
    }
}
