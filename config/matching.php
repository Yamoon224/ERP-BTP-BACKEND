<?php

/*
|--------------------------------------------------------------------------
| Moteur de rapprochement à 3 voies
|--------------------------------------------------------------------------
|
| Les tolérances vivent ici (et non en dur dans le moteur) pour deux raisons :
| elles sont réglementaires/contractuelles donc amenées à changer sans
| redéploiement, et chaque exécution du moteur en fige une copie dans
| `match_runs.tolerance_snapshot` — condition nécessaire pour rejouer et
| auditer une décision passée (règle fonctionnelle n°5).
|
*/

return [
    /*
     * Versionner le moteur permet de savoir, des années plus tard, quelle
     * logique a produit une décision archivée. À incrémenter dès que les
     * règles de rapprochement changent.
     */
    'engine_version' => env('MATCHING_ENGINE_VERSION', '1.0.0'),

    'tolerance' => [
        /*
         * Écart de prix unitaire accepté : le plus permissif des deux seuils
         * (relatif OU absolu). L'absolu évite qu'un article à très faible prix
         * unitaire parte en revue humaine pour quelques centimes d'arrondi.
         *
         * Le seuil absolu est exprimé dans la DEVISE DE RÉFÉRENCE ci-dessous,
         * puis converti dans la devise du bon de commande avant d'être
         * appliqué. La référence étant le franc CFA, le seuil suit son ordre
         * de grandeur : 0,50 avait un sens en euro, aucun en XOF, où la
         * plus petite coupure vaut déjà plusieurs unités.
         */
        'price_ratio' => (float) env('MATCHING_PRICE_TOLERANCE_RATIO', 0.01),
        'price_absolute' => (float) env('MATCHING_PRICE_TOLERANCE_ABSOLUTE', 250),

        /*
         * Sur-facturation acceptée par rapport au bon de commande. Par défaut
         * zéro : facturer plus que commandé n'est jamais un arrondi.
         */
        'quantity_ratio' => (float) env('MATCHING_QUANTITY_TOLERANCE_RATIO', 0.0),
        'quantity_absolute' => (float) env('MATCHING_QUANTITY_TOLERANCE_ABSOLUTE', 0.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Devises
    |--------------------------------------------------------------------------
    |
    | Trois devises coexistent dans le circuit, et les confondre serait une
    | erreur :
    |
    |  - la devise de la FACTURE      : ce que le fournisseur réclame et
    |    recevra ;
    |  - la devise du BON DE COMMANDE : référence contractuelle, dans laquelle
    |    les prix sont confrontés ;
    |  - la devise de RÉFÉRENCE       : unité d'agrégation du pilotage, car
    |    additionner des euros, des dollars et des francs CFA n'a aucun sens.
    |
    | La conversion s'applique au taux en vigueur à la DATE DE LA FACTURE, pas
    | à celle du rapprochement : c'est la date de naissance de la créance qui
    | fait foi, et c'est ce qui rend un rapprochement rejouable à l'identique.
    |
    */

    /**
     * Devise proposée par défaut aux documents créés sans devise explicite.
     *
     * Le franc CFA est la devise d'exploitation : c'est dans cette unité que
     * les chantiers sont budgétés et que les règlements partent. Tout document
     * créé sans devise explicite naît donc en XOF, et non dans une devise
     * étrangère qu'il faudrait convertir pour la moindre lecture.
     */
    'default_currency' => env('MATCHING_DEFAULT_CURRENCY', 'XOF'),

    /**
     * Devise d'agrégation du tableau de bord et des seuils absolus.
     *
     * Identique à la devise par défaut : agréger dans une unité que personne
     * n'emploie au quotidien obligerait à reconvertir mentalement chaque total
     * du pilotage.
     */
    'base_currency' => env('MATCHING_BASE_CURRENCY', 'XOF'),
];
