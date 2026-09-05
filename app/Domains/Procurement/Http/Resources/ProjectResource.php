<?php

namespace App\Domains\Procurement\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'client_name' => $this->client_name,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
