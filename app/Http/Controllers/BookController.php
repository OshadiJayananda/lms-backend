<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    /**
     * Display a listing of books.
     */
    public function index()
    {
        $books = Book::all();
        return response()->json($books, 200);
    }

    /**
     * Store a newly created book.
     */
    public function store(BookRequest $request)
    {
        $data = $request->validated();

        // Handle Image Upload
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('book_images', 'public');
        }

        $book = Book::create($data);

        return response()->json(['message' => 'Book created successfully!', 'book' => $book], 201);
    }

    /**
     * Display the specified book.
     */
    public function show(Book $book)
    {
        return response()->json($book);
    }

    /**
     * Update the specified book.
     */
    public function update(UpdateBookRequest $request, Book $book)
    {
        // Validate the request data
        $data = $request->validated();

        // Handle Image Upload
        if ($request->hasFile('image')) {
            // Delete the old image if it exists
            if ($book->image) {
                Storage::delete('public/' . $book->image);
            }
            // Store the new image
            $data['image'] = $request->file('image')->store('book_images', 'public');
        }

        // Update the book record
        $book->update($data);

        // Return success response
        return response()->json(['message' => 'Book updated successfully!', 'book' => $book]);
    }

    /**
     * Remove the specified book.
     */
    public function destroy(Book $book)
    {
        $book->delete();
        return response()->json(['message' => 'Book deleted successfully!']);
    }
}
