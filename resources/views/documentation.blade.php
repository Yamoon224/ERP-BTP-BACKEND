@extends('layouts.shell')

@section('title', 'Documentation API - ERP BTP')

@push('head')
    {{-- Swagger UI est servi depuis nos propres assets et non depuis un CDN :
         la documentation reste consultable sans accès Internet (utile en
         environnement d'intégration cloisonné) et aucune ressource tierce ne
         peut être substituée à notre insu. Les fichiers sont copiés depuis
         swagger-ui-dist par `composer run docs:assets`. --}}
    <link rel="stylesheet" href="{{ asset('vendor/swagger-ui/swagger-ui.css') }}">
    <style>
        .docs-header { max-width: 960px; margin: 0 auto; padding: 32px 24px 20px; }
        .docs-header .actions { margin-top: 18px; }
        #swagger-ui { background: var(--surface); }
        .swagger-ui .topbar { display: none; }
        .swagger-ui { font-family: inherit; }
    </style>
@endpush

@section('body')
    <div class="docs-header">
        <p class="eyebrow">ERP BTP · Référence de l'API</p>
        <h1>Documentation API</h1>
        <p class="lead">
            Contrat complet de l'API REST : endpoints, paramètres, corps de requête,
            en-têtes, authentification, codes HTTP et forme des erreurs. Un test
            automatisé vérifie que cette spécification couvre bien toutes les routes
            réellement exposées.
        </p>
        <div class="actions">
            <a class="btn btn-secondary" href="{{ url('/') }}">&larr; Accueil</a>
            <a class="btn btn-secondary" href="{{ url('/docs/openapi.json') }}">Télécharger la spécification</a>
        </div>
    </div>

    <div id="swagger-ui"></div>

    <script src="{{ asset('vendor/swagger-ui/swagger-ui-bundle.js') }}"></script>
    <script>
        window.addEventListener('load', function () {
            window.ui = SwaggerUIBundle({
                url: @json(url('/docs/openapi.json')),
                dom_id: '#swagger-ui',
                deepLinking: true,
                docExpansion: 'list',
                defaultModelsExpandDepth: 0,
                displayRequestDuration: true,
                tryItOutEnabled: true,
                persistAuthorization: true,
            });
        });
    </script>
@endsection
