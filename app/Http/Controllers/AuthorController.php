<?php

namespace App\Http\Controllers;

use App\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthorController extends Controller
{
    /**
     * Display a listing of all authors.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $authors = Author::orderBy('name')->get();
        return response()->json($authors);
    }

    /**
     * Display the specified author.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $author = Author::with('books')->find($id);

        if (!$author) {
            return response()->json(['message' => 'Author not found'], 404);
        }

        return response()->json($author);
    }

    /**
     * Store a newly created author.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'bio' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'death_date' => 'nullable|date|after:birth_date',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $author = Author::create($request->all());

        return response()->json([
            'message' => 'Author created successfully',
            'author' => $author
        ], 201);
    }

    /**
     * Update the specified author.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $author = Author::find($id);

        if (!$author) {
            return response()->json(['message' => 'Author not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'bio' => 'nullable|string',
            'nationality' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date',
            'death_date' => 'nullable|date|after:birth_date',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $author->update($request->all());

        return response()->json([
            'message' => 'Author updated successfully',
            'author' => $author
        ]);
    }

    /**
     * Remove the specified author.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $author = Author::find($id);

        if (!$author) {
            return response()->json(['message' => 'Author not found'], 404);
        }

        // Check if author has books before deleting
        if ($author->books()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete author with associated books'
            ], 422);
        }

        $author->delete();

        return response()->json([
            'message' => 'Author deleted successfully'
        ]);
    }
}
