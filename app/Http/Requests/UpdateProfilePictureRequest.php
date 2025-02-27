<?php

namespace App\Http\Requests;

class UpdateProfilePictureRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }
}
