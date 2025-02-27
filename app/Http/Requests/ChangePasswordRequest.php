<?php

namespace App\Http\Requests;

class ChangePasswordRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'password' => 'required|min:8|confirmed',
        ];
    }
}
