<?php

namespace App\Domains\Matching\Http\Controllers;

use App\Domains\Matching\Contracts\MatchRunRepositoryContract;
use App\Domains\Matching\Http\Resources\MatchRunResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Registre global des rapprochements.
 *
 * Deux verbes seulement, et c'est deliberé.
 *
 * Une execution (`match_run`) est **immuable** : elle archive qui a decide,
 * quand, avec quelle version du moteur, quelles tolerances, et la preuve
 * chiffree ligne a ligne. La modifier reviendrait a reecrire une decision
 * passee ; la supprimer, a effacer la justification d'un paiement deja
 * autorise. Aucune des deux operations n'existe donc, ni ici ni ailleurs.
 *
 * Ce que l'on peut faire, en revanche, c'est **rejouer** : `POST
 * /invoices/{invoice}/match-runs` produit une nouvelle execution qui prend la
 * main sur la precedente sans la detruire. C'est l'equivalent fonctionnel d'une
 * correction, avec l'historique en plus.
 */
class MatchRunController extends Controller
{
    public function __construct(private readonly MatchRunRepositoryContract $matchRuns) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return MatchRunResource::collection($this->matchRuns->paginate(
            $request->only(
                'search',
                'invoice_id',
                'supplier_id',
                'status',
                'trigger',
                'actor_type',
                'sort',
                'direction',
            ),
            $request->integer('per_page', 10),
        ));
    }

    public function show(string $matchRun): MatchRunResource
    {
        return new MatchRunResource($this->matchRuns->findOrFail($matchRun));
    }
}
