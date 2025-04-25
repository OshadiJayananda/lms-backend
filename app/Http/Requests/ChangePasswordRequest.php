<?php

namespace App\Http\Requests;

class ChangePasswordRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => 'required|min:6',
            'password' => 'required|min:6|confirmed',
        ];
    }
}
