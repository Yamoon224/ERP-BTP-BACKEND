<?php

namespace App\Domains\Receiving\Contracts;

use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use App\Models\DeliveryNote;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DeliveryNoteRepositoryContract
{
    /** @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, DeliveryNote>
     */
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    public function findOrFail(int $id): DeliveryNote;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $lines
     */
    public function create(array $attributes, array $lines): DeliveryNote;

    public function updateStatus(DeliveryNote $deliveryNote, DeliveryNoteStatus $status): DeliveryNote;
}
