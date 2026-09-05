<?php

namespace App\Domains\Matching\Http\Requests;

use App\Domains\Matching\Enums\ReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewMatchExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in([
                ReviewStatus::Approved->value,
                ReviewStatus::Rejected->value,
            ])],
            // Le motif est obligatoire : un arbitrage sans justification est
            // intracable a posteriori, ce qui vide le controle de sa valeur.
            'note' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'note.required' => 'Un motif est obligatoire pour tracer la decision.',
        ];
    }

    public function decision(): ReviewStatus
    {
        return ReviewStatus::from($this->string('decision')->toString());
    }
}
