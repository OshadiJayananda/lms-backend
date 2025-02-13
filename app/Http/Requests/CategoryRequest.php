<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Set to false if only authorized users can access
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'status' => 'required|boolean',
        ];
    }
}
