<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

abstract class BaseFormRequest extends FormRequest
{
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        Log::error('Validation failed', ['errors' => $validator->errors()]);

        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(
            [
                "success"   => false,
                "data"      => [],
                "message"   => "Validation Error",
                "errors"      => $validator->errors()
            ],
            Response::HTTP_UNPROCESSABLE_ENTITY
        ));
    }
}
