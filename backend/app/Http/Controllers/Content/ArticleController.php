<?php

namespace App\Http\Controllers\Content;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ArticleController extends Controller
{
    // GET /api/articles?category_id=1  (any logged-in user)
    public function index(Request $request): JsonResponse
    {
        $request->validate(['category_id' => ['nullable', 'integer', 'exists:categories,id']]);

        $articles = Article::with('categories')
            ->when($request->category_id, function ($q, $id) {
                $q->whereHas('categories', fn ($c) => $c->where('categories.id', $id));
            })
            ->latest('id')
            ->paginate(15);

        return response()->json($articles);
    }

    // GET /api/articles/{article}
    public function show(Article $article): JsonResponse
    {
        return response()->json(['article' => $article->load('categories')]);
    }

    // POST /api/admin/articles   (multipart/form-data: title, summary, url, cover?, category_ids[]?)
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string'],
            'url' => ['required', 'url', 'max:255'],
            'cover' => ['nullable', 'image', 'max:4096'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        $article = DB::transaction(function () use ($request, $data) {
            $article = Article::create([
                'title' => $data['title'],
                'summary' => $data['summary'],
                'url' => $data['url'],
                'cover' => $request->hasFile('cover') ? $request->file('cover')->store('covers', 'public') : null,
            ]);

            $article->categories()->sync($data['category_ids'] ?? []);

            return $article;
        });

        return response()->json(['article' => $article->load('categories')], 201);
    }

    // POST /api/admin/articles/{article}   (send only the fields you want to change)
    public function update(Request $request, Article $article): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'summary' => ['sometimes', 'required', 'string'],
            'url' => ['sometimes', 'required', 'url', 'max:255'],
            'cover' => ['nullable', 'image', 'max:4096'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);

        DB::transaction(function () use ($request, $data, $article) {
            $article->fill(collect($data)->only(['title', 'summary', 'url'])->all());

            if ($request->hasFile('cover')) {
                if ($article->cover) {
                    Storage::disk('public')->delete($article->cover);
                }
                $article->cover = $request->file('cover')->store('covers', 'public');
            }

            $article->save();

            if (array_key_exists('category_ids', $data)) {
                $article->categories()->sync($data['category_ids']);
            }
        });

        return response()->json(['article' => $article->load('categories')]);
    }

    // DELETE /api/admin/articles/{article}
    public function destroy(Article $article): JsonResponse
    {
        if ($article->cover) {
            Storage::disk('public')->delete($article->cover);
        }

        $article->delete();

        return response()->json(['message' => 'تم الحذف.']);
    }
}
