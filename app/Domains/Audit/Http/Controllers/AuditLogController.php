<?php

namespace App\Domains\Audit\Http\Controllers;

use App\Domains\Audit\Http\Resources\AuditLogResource;
use App\Domains\Audit\Services\AuditLogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Journal d'audit : qui a change quoi, quand, et sur quel objet.
 *
 * Lecture seule, par construction. Il n'y a ni `store`, ni `update`, ni
 * `destroy` — un journal que l'on peut editer ne prouve rien. La retention se
 * regle par une purge planifiee cote exploitation, jamais par un appel HTTP.
 */
class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogs) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return AuditLogResource::collection($this->auditLogs->list(
            $request->only(
                'search',
                'event',
                'subject_type',
                'subject_id',
                'causer_id',
                'from',
                'to',
                'sort',
                'direction',
            ),
            $request->integer('per_page', 10),
        ));
    }

    public function show(string $auditLog): AuditLogResource
    {
        return new AuditLogResource($this->auditLogs->find($auditLog));
    }

    /** Valeurs disponibles pour les filtres, calculees sur les donnees reelles. */
    public function facets(): JsonResponse
    {
        return response()->json(['data' => $this->auditLogs->facets()]);
    }
}
