<?php

namespace App\Helpers;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class Validations
{
    public static function validateUser(array $data)
    {
        return Validator::make($data, [
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'email'         => [
                'required',
                'email',
                Rule::unique('store_users')->where(function ($query) {
                    return $query->whereNull('deleted_at');
                }),
            ],
            'phone'         => 'required|string|max:50',
            'image'         => 'nullable|string',
            'theme'         => 'nullable|string|max:100',
            'role'          => 'required|string|in:super-admin,admin,user',
            'gender'        => 'nullable|string|in:male,female',
            'year_of_birth' => 'nullable|integer|min:1900',
            'city'          => 'nullable|string',
            'country'       => 'nullable|string|max:255',
            'postal_code'   => 'nullable|string|max:20',
        ]);
    }

    public static function validateSubscription(array $data)
    {
        return Validator::make($data, [
            'email' => [
                'required',
                'email',
                Rule::unique('subscriptions')->where(function ($query) {
                    return $query->whereNull('deleted_at');
                }),
            ],
        ]);
    }
}
