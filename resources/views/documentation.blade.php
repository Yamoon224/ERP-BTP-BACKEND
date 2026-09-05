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
        /*
            Cette page assume un thème clair, et un seul.

            La feuille de style de Swagger UI est écrite en clair et n'expose
            aucune variable : sous le thème sombre hérité de la coquille, on
            obtenait du gris foncé sur des blocs blancs posés sur un fond noir —
            la moitié de la page devenait illisible. Plutôt que de repeindre les
            quelques centaines de règles du paquet, la documentation garde une
            apparence unique, la sienne.
        */
        :root, .swagger-ui { color-scheme: light; }

        :root {
            --bg: #f7f8fa;
            --surface: #ffffff;
            --surface-sunken: #f2f4f7;
            --border: #e3e7ee;
            --border-strong: #cbd3e0;
            --text: #17202c;
            --muted: #5b6779;
            --accent: #1d4ed8;
            --accent-hover: #1a43b8;
            --accent-soft: #eef3ff;
        }

        body { background: var(--bg); color: var(--text); }

        /* --- En-tête -------------------------------------------------------- */

        .docs-masthead {
            border-bottom: 1px solid var(--border);
            background: linear-gradient(180deg, #ffffff 0%, var(--bg) 100%);
        }

        .docs-masthead .inner { max-width: 1180px; margin: 0 auto; padding: 44px 28px 30px; }
        .docs-masthead h1 { font-size: 32px; margin: 0 0 10px; }
        .docs-masthead .lead { max-width: 68ch; }
        .docs-masthead .actions { margin-top: 24px; }

        .docs-facts {
            display: flex; flex-wrap: wrap; gap: 8px;
            margin: 22px 0 0; padding: 0; list-style: none;
        }
        .docs-facts li {
            font-size: 12px; font-weight: 600; color: var(--muted);
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 999px; padding: 5px 12px;
        }
        .docs-facts li code { background: none; padding: 0; font-size: 12px; }

        /* --- Swagger UI ------------------------------------------------------ */

        #swagger-ui { max-width: 1180px; margin: 0 auto; padding: 8px 14px 72px; }

        .swagger-ui { font-family: inherit; color: var(--text); }

        /* La bannière du paquet et le titre répété par le bloc « info » disent
           ce que l'en-tête ci-dessus dit déjà. Le corps de la description, lui,
           est le mode d'emploi du contrat : il reste, mis en page comme un
           document et non comme un encart d'outil. */
        .swagger-ui .topbar,
        .swagger-ui .info hgroup.main,
        .swagger-ui .scheme-container .schemes-title { display: none; }

        .swagger-ui .information-container { padding: 8px 0 0; }
        .swagger-ui .info { margin: 24px 0 0; }
        .swagger-ui .info .description {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 8px 26px 22px;
        }
        .swagger-ui .info .description h2 {
            font-size: 15px; font-weight: 650; color: var(--text);
            margin: 26px 0 8px; letter-spacing: -0.01em;
        }
        .swagger-ui .info .description p,
        .swagger-ui .info .description li { color: var(--muted); font-size: 14px; }
        .swagger-ui .info .description strong { color: var(--text); }
        .swagger-ui .info .description code {
            background: var(--accent-soft); color: var(--accent);
            border-radius: 4px; padding: 2px 6px; font-size: 12.5px;
        }

        .swagger-ui .scheme-container {
            background: transparent; box-shadow: none; margin: 0; padding: 20px 0 6px;
        }

        .swagger-ui .btn {
            border-radius: 8px; font-weight: 600; box-shadow: none;
            border-color: var(--border-strong); color: var(--text);
            transition: border-color .15s, background-color .15s;
        }
        .swagger-ui .btn:hover { border-color: var(--accent); }
        .swagger-ui .btn.authorize { color: var(--accent); border-color: var(--accent); }
        .swagger-ui .btn.authorize svg { fill: var(--accent); }
        .swagger-ui .btn.execute {
            background: var(--accent); border-color: var(--accent); color: #fff;
        }
        .swagger-ui .btn.execute:hover { background: var(--accent-hover); border-color: var(--accent-hover); }

        /* Un tag est un intertitre, pas une bannière. */
        .swagger-ui .opblock-tag {
            font-size: 17px; font-weight: 650; letter-spacing: -0.01em; color: var(--text);
            border-bottom: 1px solid var(--border); padding: 26px 0 12px; margin: 6px 0;
        }
        .swagger-ui .opblock-tag:hover { background: transparent; }
        .swagger-ui .opblock-tag small { color: var(--muted); font-size: 13px; font-weight: 400; }

        /* Une opération = une carte sobre. La couleur du verbe se lit sur la
           pastille et sur le liseré gauche ; le reste reste neutre, sinon la
           page clignote dès qu'on déplie trois endpoints. */
        .swagger-ui .opblock {
            border: 1px solid var(--border); border-radius: 10px;
            background: var(--surface); box-shadow: none; margin: 0 0 10px;
        }
        .swagger-ui .opblock.is-open { border-color: var(--border-strong); }
        .swagger-ui .opblock .opblock-summary { border-bottom: none; padding: 6px 10px; }
        .swagger-ui .opblock.is-open .opblock-summary { border-bottom: 1px solid var(--border); }

        .swagger-ui .opblock .opblock-summary-method {
            border-radius: 6px; font-size: 12px; font-weight: 700;
            min-width: 76px; padding: 7px 0; text-shadow: none; box-shadow: none;
        }
        .swagger-ui .opblock .opblock-summary-path,
        .swagger-ui .opblock .opblock-summary-path__deprecated {
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace;
            font-size: 13.5px; color: var(--text);
        }
        .swagger-ui .opblock .opblock-summary-description { color: var(--muted); font-size: 13px; }

        .swagger-ui .opblock.opblock-get { border-left: 3px solid #1d78c9; }
        .swagger-ui .opblock.opblock-post { border-left: 3px solid #0f7b56; }
        .swagger-ui .opblock.opblock-patch { border-left: 3px solid #8a6d1f; }
        .swagger-ui .opblock.opblock-put { border-left: 3px solid #b45309; }
        .swagger-ui .opblock.opblock-delete { border-left: 3px solid #b42318; }

        .swagger-ui .opblock-body,
        .swagger-ui .opblock .opblock-section-header { background: var(--surface); box-shadow: none; }
        .swagger-ui .opblock .opblock-section-header {
            border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);
        }
        .swagger-ui .opblock-description-wrapper p,
        .swagger-ui .opblock-external-docs-wrapper p,
        .swagger-ui .renderedMarkdown p { color: var(--muted); font-size: 14px; }

        .swagger-ui table thead tr th,
        .swagger-ui table thead tr td {
            border-bottom: 1px solid var(--border); color: var(--muted);
            font-size: 11.5px; letter-spacing: .05em; text-transform: uppercase;
        }
        .swagger-ui .parameter__name { font-weight: 600; }
        .swagger-ui .parameter__type,
        .swagger-ui .prop-format { color: var(--muted); }

        .swagger-ui select,
        .swagger-ui input[type=text],
        .swagger-ui textarea {
            border: 1px solid var(--border-strong); border-radius: 8px;
            box-shadow: none; background: var(--surface); color: var(--text);
        }
        .swagger-ui select:focus,
        .swagger-ui input[type=text]:focus,
        .swagger-ui textarea:focus {
            outline: 3px solid var(--accent-soft); border-color: var(--accent);
        }

        .swagger-ui .highlight-code > .microlight,
        .swagger-ui .model-box { border-radius: 8px; }
        .swagger-ui .model-box { background: var(--surface-sunken); }
        .swagger-ui .response-col_status { font-weight: 600; }
        .swagger-ui .tab li { color: var(--muted); }

        @media (max-width: 640px) {
            .docs-masthead .inner { padding: 30px 18px 24px; }
            .docs-masthead h1 { font-size: 26px; }
            #swagger-ui { padding: 4px 8px 56px; }
        }
    </style>
@endpush

@section('body')
    <header class="docs-masthead">
        <div class="inner">
            <p class="eyebrow">ERP BTP · Référence de l'API</p>
            <h1>Documentation API</h1>
            <p class="lead">
                Contrat complet de l'API REST : endpoints, paramètres, corps de requête,
                en-têtes, authentification, codes HTTP et forme des erreurs. Un test
                automatisé vérifie que cette spécification couvre bien toutes les routes
                réellement exposées.
            </p>

            <ul class="docs-facts">
                <li>Jeton porteur, expiration bornée</li>
                <li>Identifiants au format UUID</li>
                <li>Erreurs normalisées : <code>error_code</code></li>
            </ul>

            <div class="actions">
                <a class="btn btn-secondary" href="{{ url('/') }}">&larr; Accueil</a>
                <a class="btn btn-secondary" href="{{ url('/docs/openapi.json') }}">Télécharger la spécification</a>
            </div>
        </div>
    </header>

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
                syntaxHighlight: { theme: 'idea' },
            });
        });
    </script>
@endsection
