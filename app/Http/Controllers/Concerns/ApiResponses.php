<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Enveloppe de reponse unique pour les charges utiles composites.
 *
 * Les ressources Laravel encapsulent deja sous `data` ; ce helper etend la
 * meme convention aux reponses qui agregent plusieurs ressources, pour qu un
 * client n ait jamais a deviner si la charge utile est a la racine ou sous
 * `data`.
 */
trait ApiResponses
{
    /** @param  array<string, mixed>  $data */
    protected function ok(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }
}
