<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class SignupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'first_name' => ['required', 'string'],
            'last_name' => ['nullable', 'string'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->where(fn($q) => $q->whereNull('deleted_at')),
            ],
            'password' => ['required', Password::min(8)],
            'role' => ['nullable', 'string', Rule::in(['student', 'instructor'])],
        ];
    }
}
