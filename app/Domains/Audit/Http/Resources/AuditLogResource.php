<?php

namespace App\Domains\Audit\Http\Resources;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une entree du journal, rendue lisible.
 *
 * Le type du sujet est traduit en libelle metier : `App\Models\MatchException`
 * ne dit rien a un controleur financier, « Ecart de rapprochement » si. Le nom
 * de classe brut reste servi a cote — c'est lui qui sert de cle de filtre, et
 * l'ecran ne doit pas avoir a retraduire dans l'autre sens.
 */
class AuditLogResource extends JsonResource
{
    /** @var array<string, string> */
    private const SUBJECT_LABELS = [
        'App\Models\Supplier' => 'Fournisseur',
        'App\Models\Project' => 'Chantier',
        'App\Models\PurchaseOrder' => 'Bon de commande',
        'App\Models\PurchaseOrderLine' => 'Ligne de bon de commande',
        'App\Models\DeliveryNote' => 'Bon de livraison',
        'App\Models\Invoice' => 'Facture',
        'App\Models\MatchRun' => 'Rapprochement',
        'App\Models\MatchException' => 'Ecart de rapprochement',
        'App\Models\PaymentAuthorization' => 'Autorisation de paiement',
        'App\Models\ExchangeRate' => 'Taux de change',
        'App\Models\User' => 'Utilisateur',
    ];

    /** @var array<string, string> */
    private const EVENT_LABELS = [
        'created' => 'Creation',
        'updated' => 'Modification',
        'deleted' => 'Suppression',
        'restored' => 'Restauration',
    ];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ActivityLog $activity */
        $activity = $this->resource;

        return [
            'id' => $activity->id,
            'log_name' => $activity->log_name,
            'description' => $activity->description,
            'event' => $activity->event,
            'event_label' => self::EVENT_LABELS[$activity->event ?? ''] ?? ($activity->event ?? '—'),

            'subject_type' => $activity->subject_type,
            'subject_label' => self::SUBJECT_LABELS[$activity->subject_type ?? ''] ?? $this->shortClass($activity->subject_type),
            'subject_id' => $activity->subject_id,

            // L'auteur peut etre absent : une decision du moteur n'a pas de
            // causer. L'afficher comme « Systeme » plutot que vide evite de
            // laisser croire a une information manquante.
            'causer' => $activity->causer === null ? null : [
                'id' => $activity->causer->getKey(),
                'name' => $activity->causer->getAttribute('name'),
            ],
            'causer_label' => $activity->causer?->getAttribute('name') ?? 'Systeme',

            // Ce qui a change, tel que le trait l'a archive : `attributes`
            // (apres) et `old` (avant).
            'properties' => $activity->properties?->toArray() ?? [],

            'created_at' => $activity->created_at?->toIso8601String(),
        ];
    }

    private function shortClass(?string $class): string
    {
        if ($class === null || $class === '') {
            return '—';
        }

        $parts = explode('\\', $class);

        return end($parts);
    }
}
