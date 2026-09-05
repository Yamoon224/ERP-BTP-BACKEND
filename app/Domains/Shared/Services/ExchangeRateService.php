<?php

namespace App\Domains\Shared\Services;

use App\Domains\Shared\Contracts\ExchangeRateRepositoryContract;
use App\Domains\Shared\Enums\ExchangeRateSource;
use App\Domains\Shared\Exceptions\FixedPegNotEditableException;
use App\Models\ExchangeRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Administration du referentiel de taux.
 *
 * Les taux ne sont jamais ecrases « sur place » au fil de l'eau : chaque
 * cotation vaut a partir d'une date d'effet, et c'est la superposition de ces
 * dates qui permet de rejouer a l'identique un rapprochement d'il y a six
 * mois. Corriger une saisie fautive reste possible ; changer le cours du jour
 * se fait en ajoutant une ligne, pas en modifiant la precedente.
 */
final class ExchangeRateService
{
    public function __construct(private readonly ExchangeRateRepositoryContract $rates) {}

    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ExchangeRate>
     */
    public function list(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->rates->paginate($filters, $perPage);
    }

    public function find(string $id): ExchangeRate
    {
        return $this->rates->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): ExchangeRate
    {
        return $this->rates->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws FixedPegNotEditableException
     */
    public function update(ExchangeRate $rate, array $data): ExchangeRate
    {
        $this->assertNotFixedPeg($rate);

        return $this->rates->update($rate, $data);
    }

    /** @throws FixedPegNotEditableException */
    public function delete(ExchangeRate $rate): void
    {
        $this->assertNotFixedPeg($rate);

        $this->rates->delete($rate);
    }

    /** @throws FixedPegNotEditableException */
    private function assertNotFixedPeg(ExchangeRate $rate): void
    {
        if ($rate->source === ExchangeRateSource::FixedPeg) {
            throw FixedPegNotEditableException::make(
                "{$rate->base_currency->value}/{$rate->quote_currency->value}",
            );
        }
    }
}
