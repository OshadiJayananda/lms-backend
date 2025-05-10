<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Book;

class UpdateBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Change this if authorization logic is needed
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $bookId = $this->route('book')?->id; // Get the book id from the route

        return [
            'name' => 'nullable|string|max:255',
            'author_id' => 'nullable|exists:authors,id',
            'isbn' => [
                'nullable',
                'string',
                'max:13',
                'unique:books,isbn,' . $bookId,
            ],
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'nullable|string',
            'no_of_copies' => 'nullable|integer|min:1',
            'category_id' => 'nullable|exists:categories,id',
        ];
    }

    /**
     * Get custom attributes for the validator errors.
     */
    public function attributes()
    {
        return [
            'name' => 'book title',
            'author' => 'book author',
            'isbn' => 'book ISBN',
            'image' => 'book image',
            'description' => 'book description',
            'no_of_copies' => 'number of copies',
            'category_id' => 'book category',
        ];
    }
}
