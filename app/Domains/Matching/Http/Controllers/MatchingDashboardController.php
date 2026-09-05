<?php

namespace App\Domains\Matching\Http\Controllers;

use App\Domains\Matching\Services\MatchingDashboardService;
use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MatchingDashboardController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly MatchingDashboardService $dashboard) {}

    public function __invoke(): JsonResponse
    {
        return $this->ok($this->dashboard->summary());
    }
}
