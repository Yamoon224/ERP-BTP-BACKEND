<?php

namespace App\Domains\Shared\Contracts;

use App\Models\ExchangeRate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Ecriture et consultation du referentiel de taux.
 *
 * Distinct de `ExchangeRateProviderContract`, qui ne sait que *resoudre* le
 * taux applicable a une date : la resolution est une lecture du moteur, la
 * gestion est un acte d'administration. Les separer evite qu'un ecran de
 * saisie puisse influencer le chemin de decision du rapprochement.
 */
interface ExchangeRateRepositoryContract
{
    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ExchangeRate>
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function findOrFail(string $id): ExchangeRate;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): ExchangeRate;

    /** @param  array<string, mixed>  $attributes */
    public function update(ExchangeRate $rate, array $attributes): ExchangeRate;

    public function delete(ExchangeRate $rate): void;
}
