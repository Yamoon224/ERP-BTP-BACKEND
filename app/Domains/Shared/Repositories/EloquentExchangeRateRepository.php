<?php

namespace App\Domains\Shared\Repositories;

use App\Domains\Shared\Contracts\ExchangeRateRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\ExchangeRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentExchangeRateRepository implements ExchangeRateRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'id' => 'id',
        'base_currency' => 'base_currency',
        'quote_currency' => 'quote_currency',
        'rate' => 'rate',
        'source' => 'source',
        'effective_from' => 'effective_from',
    ];

    /** @return LengthAwarePaginator<int, ExchangeRate> */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return ExchangeRate::query()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($subQuery) => $subQuery
                    ->where('base_currency', 'like', "%{$search}%")
                    ->orWhere('quote_currency', 'like', "%{$search}%"),
            ))
            ->when($filters['base_currency'] ?? null, fn ($query, $code) => $query->where('base_currency', $code))
            ->when($filters['quote_currency'] ?? null, fn ($query, $code) => $query->where('quote_currency', $code))
            ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            // Le plus recent d'abord : c'est le taux qui s'applique aujourd'hui,
            // et donc celui qu'on vient verifier neuf fois sur dix.
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'effective_from', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(int $id): ExchangeRate
    {
        return ExchangeRate::findOrFail($id);
    }

    public function create(array $attributes): ExchangeRate
    {
        return ExchangeRate::create($attributes);
    }

    public function update(ExchangeRate $rate, array $attributes): ExchangeRate
    {
        $rate->update($attributes);

        return $rate->refresh();
    }

    public function delete(ExchangeRate $rate): void
    {
        $rate->delete();
    }
}
