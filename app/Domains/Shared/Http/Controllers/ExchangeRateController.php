<?php

namespace App\Domains\Shared\Http\Controllers;

use App\Domains\Shared\Http\Requests\StoreExchangeRateRequest;
use App\Domains\Shared\Http\Requests\UpdateExchangeRateRequest;
use App\Domains\Shared\Http\Resources\ExchangeRateResource;
use App\Domains\Shared\Services\ExchangeRateService;
use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ExchangeRateController extends Controller
{
    public function __construct(private readonly ExchangeRateService $exchangeRates) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ExchangeRateResource::collection($this->exchangeRates->list(
            $request->only('search', 'base_currency', 'quote_currency', 'source', 'sort', 'direction'),
            $request->integer('per_page', 10),
        ));
    }

    public function store(StoreExchangeRateRequest $request): JsonResponse
    {
        $rate = $this->exchangeRates->create($request->validated());

        return (new ExchangeRateResource($rate))->response()->setStatusCode(201);
    }

    public function show(ExchangeRate $exchangeRate): ExchangeRateResource
    {
        return new ExchangeRateResource($exchangeRate);
    }

    public function update(UpdateExchangeRateRequest $request, ExchangeRate $exchangeRate): ExchangeRateResource
    {
        return new ExchangeRateResource(
            $this->exchangeRates->update($exchangeRate, $request->validated()),
        );
    }

    public function destroy(ExchangeRate $exchangeRate): Response
    {
        $this->exchangeRates->delete($exchangeRate);

        return response()->noContent();
    }
}
