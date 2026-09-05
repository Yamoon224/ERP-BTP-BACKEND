<?php

/*
|--------------------------------------------------------------------------
| CORS
|--------------------------------------------------------------------------
|
| Le frontend Next.js vit sur une autre origine que l API. Les origines
| autorisees sont lues dans l environnement plutot qu ecrites en dur : un
| deploiement de recette ou de production n a pas la meme URL front que le
| poste de developpement, et `*` serait inacceptable pour une API qui expose
| des donnees financieres.
|
*/

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:3000'))),
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Authentification par token Bearer, pas par cookie de session : aucune
    // raison d autoriser l envoi de credentials cross-origin.
    'supports_credentials' => false,
];
