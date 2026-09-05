<?php

namespace App\Domains\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Changement de mot de passe par son proprietaire.
 *
 * L'ancien mot de passe est exige meme si l'utilisateur est deja authentifie :
 * un jeton vole permettrait sinon de verrouiller le compte de sa victime en un
 * appel.
 */
class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.confirmed' => 'La confirmation ne correspond pas au nouveau mot de passe.',
        ];
    }
}
