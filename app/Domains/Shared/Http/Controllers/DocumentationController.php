<?php

namespace App\Domains\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\Yaml\Yaml;

/**
 * Sert la specification OpenAPI et son interface Swagger UI.
 *
 * La specification est ecrite a la main (approche « spec first ») plutot que
 * generee depuis des annotations : elle documente le contrat tel qu il est
 * promis aux consommateurs, sans encombrer les controleurs de dizaines de
 * lignes d annotations. Sa coherence avec les routes reellement enregistrees
 * est verifiee par un test automatise (OpenApiSpecificationTest), ce qui
 * empeche la documentation de deriver silencieusement.
 */
class DocumentationController extends Controller
{
    public const SPECIFICATION_PATH = 'openapi/openapi.yaml';

    public function ui(): View
    {
        return view('documentation');
    }

    /** Specification convertie en JSON, consommee par Swagger UI. */
    public function specification(): JsonResponse
    {
        return response()->json(self::specificationArray());
    }

    /** @return array<string, mixed> */
    public static function specificationArray(): array
    {
        return Yaml::parse(File::get(resource_path(self::SPECIFICATION_PATH)));
    }
}
