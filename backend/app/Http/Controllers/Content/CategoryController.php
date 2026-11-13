<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    // GET /api/categories  (any logged-in user)
    public function index(): JsonResponse
    {
        return response()->json(['categories' => Category::orderBy('name')->get()]);
    }

    // POST /api/admin/categories
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
        ]);

        return response()->json(['category' => Category::create($data)], 201);
    }

    // POST /api/admin/categories/{category}
    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)],
        ]);

        $category->update($data);

        return response()->json(['category' => $category]);
    }

    // DELETE /api/admin/categories/{category}
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => 'تم الحذف.']);
    }
}
