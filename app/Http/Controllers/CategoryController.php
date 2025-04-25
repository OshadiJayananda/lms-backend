<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Http\Requests\CategoryRequest;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Category::with('parentCategory')
                ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('name');

            if ($request->get('parents_only')) {
                $query->whereNull('parent_id');
            }

            return response()->json($query->paginate(5), Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(CategoryRequest $request)
    {
        try {
            $category = Category::create($request->validated());
            return response()->json([
                'message' => 'Category created successfully',
                'category' => $category->load('parentCategory'),
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function show(Category $category)
    {
        return response()->json(
            $category->load('parentCategory', 'childCategories'),
            Response::HTTP_OK
        );
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $category->update($request->validated());
        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category->fresh(['parentCategory', 'childCategories']),
        ], Response::HTTP_OK);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json([
            'message' => 'Category deleted successfully'
        ], Response::HTTP_OK);
    }
}
