<?php

namespace App\Domains\Receiving\Http\Requests;

use App\Domains\Receiving\Enums\DeliveryNoteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewDeliveryNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Seuls accepted/rejected sont recevables : on ne remet pas un BL
            // controle en brouillon.
            'status' => ['required', 'string', Rule::in([
                DeliveryNoteStatus::Accepted->value,
                DeliveryNoteStatus::Rejected->value,
            ])],
        ];
    }

    public function decision(): DeliveryNoteStatus
    {
        return DeliveryNoteStatus::from($this->string('status')->toString());
    }
}
