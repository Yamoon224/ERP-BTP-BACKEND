# Architecture backend — ERP BTP / Rapprochement à 3 voies

Reprend les conventions du projet `clinic-manager`, avec `app/Modules/` renommé
en `app/Domains/` conformément à la demande.

## Organisation par domaine métier

```
app/Domains/{Domaine}/
    Contracts/       Interfaces (dépôts, services) — les frontières du domaine
    Enums/           Vocabulaire métier typé, avec libellés et règles associées
    Repositories/    Implémentations Eloquent des contrats
    Services/        Logique applicative et orchestration
    Exceptions/      Erreurs métier portant leur code HTTP et leur code applicatif
    Http/
        Controllers/ Contrôleurs minces : reçoivent, font valider, délèguent, répondent
        Requests/    FormRequests : validation des entrées
        Resources/   JsonResource : forme des réponses API
```

Domaines : `Auth`, `Procurement`, `Receiving`, `Invoicing`, `Matching`,
`Payments`, `Shared`.

`Shared` porte le socle transverse : l'enum `Currency` (avec le nombre de
décimales propre à chaque devise), le convertisseur, le fournisseur de taux et
son contrat, la sonde de santé et la documentation.

Le domaine `Matching` ajoute trois dossiers propres à sa nature :

```
    DTOs/            Photographie immuable des trois voies, et verdict produit
    Engines/         Le moteur lui-même — aucune dépendance à la persistance
    Policies/        Politiques de tolérance interchangeables
```

Les modèles Eloquent restent dans `app/Models/`, comme dans `clinic-manager` :
ils sont partagés entre domaines (une facture est lue par `Invoicing`, `Matching`
et `Payments`), et les dupliquer par domaine créerait des dépendances croisées
pires que le partage.

## Le moteur, isolé derrière un contrat

`ThreeWayMatchingEngine` implémente `MatchingEngineContract` et ne lit ni n'écrit
en base. Il reçoit un `InvoiceMatchInput` — assemblé par `MatchInputAssembler` —
et rend un `MatchOutcome`.

Cette séparation donne trois propriétés :

1. les règles se testent unitairement, sans base ni conteneur ;
2. une décision archivée peut être rejouée à l'identique sur ses données d'époque ;
3. un moteur alternatif (rapprochement à 2 voies pour les prestations sans BL)
   se substitue en changeant un *binding*.

L'orchestration vit dans `InvoiceMatchingService` : assembler → évaluer →
archiver → autoriser le paiement → en déduire l'état de la facture. Ce service ne
contient **aucune** règle de rapprochement.

## Devises et conversion

Une facture peut être libellée dans une autre devise que son bon de commande. La
résolution des taux vit dans `Shared` derrière `ExchangeRateProviderContract`, et
**le moteur ne résout jamais un taux lui-même** : `MatchInputAssembler` les
résout une fois, à la date de la facture, et les transporte dans l'entrée. Le
moteur reste ainsi une fonction pure de son entrée, donc rejouable à l'identique.

Les prix sont comparés dans la devise du bon de commande (la référence
contractuelle) ; les montants restent dans celle de la facture (le règlement) ;
les cumuls du pilotage passent par une devise de référence. Les taux appliqués
sont archivés dans `match_runs.exchange_rate_snapshot`, au même titre que les
tolérances.

## Inversion des dépendances

`app/Providers/DomainServiceProvider.php` est le point unique de câblage entre
contrats et implémentations. Aucun service métier ne référence une classe
concrète de persistance.

Deux contrats méritent l'attention, parce qu'ils portent la ségrégation
d'interfaces : `ReceivedQuantityReaderContract` (exposé par `Receiving`) et
`ConsumedQuantityReaderContract` (exposé par `Matching`) n'exposent au moteur
qu'une lecture agrégée. Le moteur n'a jamais accès au dépôt complet des bons de
livraison — il n'a aucun besoin d'en créer un.

## Piste d'audit

`match_runs` et `match_line_results` sont **append-only**. Chaque exécution
archive :

- **qui** : `actor_type` (`system` / `user`) et `actor_id` ;
- **quand** : `evaluated_at` ;
- **sur quelle base** : `engine_version`, `tolerance_snapshot` (copie figée des
  seuils appliqués) et, par ligne, `evidence` — les agrégats ayant servi au calcul.

Rejouer un rapprochement crée une nouvelle exécution ; l'autorisation de paiement
précédente passe en `superseded` et reste consultable. Rien n'est jamais écrasé.

`spatie/laravel-activitylog` complète ce dispositif sur les modèles sensibles
(PO, BL, factures, écarts, autorisations) pour tracer les modifications hors
rapprochement.

Lorsqu'une conversion intervient, `exchange_rate_snapshot` archive le taux, sa
source et sa date d'effet. Sans lui, un montant converti serait un chiffre
invérifiable.

## Gestion des erreurs

Toutes les erreurs métier héritent de `App\Domains\Shared\Exceptions\DomainException`
et portent leur code HTTP et un `error_code` applicatif stable. Le rendu JSON est
centralisé dans `bootstrap/app.php` : les contrôleurs n'interceptent jamais
d'exception.

## Rôles et permissions

`RolesAndPermissionsSeeder` définit cinq rôles et dix permissions selon une règle
de séparation des tâches : personne ne cumule « je commande », « je réceptionne »
et « je débloque le paiement ». En particulier, le comptable qui saisit les
factures ne peut pas arbitrer les écarts qu'elles déclenchent — seul le rôle
`controller` détient `matching.review`.

## Base de données

MySQL 8, y compris pour les tests d'intégration : une suite verte sur SQLite ne
prouverait rien des longueurs d'index, des types de colonnes ni du comportement
des clés étrangères réellement appliqués en production. La base de test est
distincte de la base applicative (`phpunit.xml`).

## Authentification

Laravel Sanctum en mode token : le frontend Next.js et l'API sont sur deux
origines distinctes, sans session partagée. Le client envoie
`Authorization: Bearer {token}`.

## Documentation de l'API

Spécification OpenAPI écrite en amont dans `resources/openapi/openapi.yaml`,
servie via `/docs` (Swagger UI, assets locaux) et `/docs/openapi.json`.

`tests/Feature/Documentation/OpenApiSpecificationTest.php` échoue si la
spécification et les routes divergent, ou si un `enum` documenté ne reflète plus
l'enum PHP. La cohérence documentation/code est donc vérifiée, pas espérée.
