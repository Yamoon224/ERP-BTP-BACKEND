@extends('layouts.shell')

@section('title', 'ERP BTP - API de rapprochement à 3 voies')

@section('body')
    <div class="wrap">
        <header class="masthead">
            <p class="eyebrow">ERP BTP · Contrôle financier fournisseurs</p>
            <h1>API de rapprochement à 3 voies</h1>
            <p class="lead">
                Cette API applique le contrôle du rapprochement à 3 voies : un règlement fournisseur
                n'est autorisé que pour la portion d'une facture couverte à la fois par un
                <strong>bon de commande</strong>, une <strong>réception acceptée</strong> et un
                <strong>prix conforme</strong>. Toute portion non couverte reste non payable ; tout
                écart part en revue humaine plutôt que d'être accepté ou rejeté silencieusement.
            </p>

            <div class="actions">
                <a class="btn btn-primary" href="{{ url('/docs') }}">
                    Documentation API
                </a>
                <a class="btn btn-secondary" href="{{ url('/docs/openapi.json') }}">
                    Spécification OpenAPI (JSON)
                </a>
                <a class="btn btn-secondary" href="{{ url('/api/health') }}">
                    Health check
                </a>
            </div>
        </header>

        <h2>Les trois voies</h2>
        <div class="grid">
            <div class="card">
                <h3>1 · Bon de commande</h3>
                <p>Ce qui a été engagé : quantités et prix unitaires autorisés à l'achat. Plafond absolu du paiement.</p>
            </div>
            <div class="card">
                <h3>2 · Bon de livraison</h3>
                <p>Ce qui est réellement arrivé. Seule une réception <em>acceptée</em> compte - un bon saisi mais non contrôlé n'ouvre aucun droit.</p>
            </div>
            <div class="card">
                <h3>3 · Facture</h3>
                <p>Ce qui est réclamé. Rapprochée dès sa soumission ; la portion couverte devient payable, le reste attend.</p>
            </div>
        </div>

        <h2>Garanties du moteur</h2>
        <div class="grid">
            <div class="card">
                <h3>Paiement partiel</h3>
                <p>Une livraison partielle n'autorise que la part reçue. Le solde reste bloqué jusqu'à la livraison suivante.</p>
            </div>
            <div class="card">
                <h3>Anti-double paiement</h3>
                <p>Les quantités déjà rapprochées par une autre facture sont déduites : une même livraison ne se paie jamais deux fois.</p>
            </div>
            <div class="card">
                <h3>Traçabilité intégrale</h3>
                <p>Chaque décision archive qui a tranché, quand, avec quelle version du moteur, quelles tolérances et sur quels chiffres.</p>
            </div>
            <div class="card">
                <h3>Séparation des tâches</h3>
                <p>Commander, réceptionner et débloquer un paiement relèvent de trois permissions distinctes.</p>
            </div>
        </div>

        <h2>Configuration active</h2>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Paramètre</th><th>Valeur</th><th>Effet</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Version du moteur</td>
                        <td><code>{{ $engineVersion }}</code></td>
                        <td>Archivée dans chaque décision, pour pouvoir la rejouer plus tard.</td>
                    </tr>
                    <tr>
                        <td>Tolérance de prix (relative)</td>
                        <td><code>{{ rtrim(rtrim(number_format($tolerance['price_ratio'] * 100, 2, ',', ' '), '0'), ',') }} %</code></td>
                        <td>Au-delà, la ligne part en revue et n'autorise aucun paiement.</td>
                    </tr>
                    <tr>
                        <td>Tolérance de prix (absolue)</td>
                        <td><code>{{ number_format($tolerance['price_absolute'], 2, ',', ' ') }} / unité</code></td>
                        <td>Évite qu'un article à faible prix parte en revue pour un centime d'arrondi.</td>
                    </tr>
                    <tr>
                        <td>Tolérance de quantité</td>
                        <td><code>{{ rtrim(rtrim(number_format($tolerance['quantity_ratio'] * 100, 2, ',', ' '), '0'), ',') }} %</code></td>
                        <td>Sur-facturation acceptée sans revue. Zéro par défaut : facturer plus que commandé n'est pas un arrondi.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <footer class="foot">
            Backend Laravel {{ app()->version() }} · PHP {{ PHP_VERSION }} ·
            Le frontend Next.js consomme cette API exclusivement en REST.
        </footer>
    </div>
@endsection
