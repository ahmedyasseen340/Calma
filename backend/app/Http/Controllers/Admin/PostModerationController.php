<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Moderation: the admin sees every post WITH its real author (patient posts are anonymous for everybody
 * else) and can delete any post that breaks the rules.
 */
class PostModerationController extends Controller
{
    // GET /api/admin/posts?type=doctor|patient
    public function index(Request $request): JsonResponse
    {
        $request->validate(['type' => ['nullable', 'in:doctor,patient']]);

        $posts = Post::with('user:id,first_name,last_name,email,role')
            ->when($request->type, fn ($q, $t) => $q->whereHas('user', fn ($u) => $u->where('role', $t)))
            ->latest('id')
            ->paginate(15);

        return response()->json($posts);
    }

    // DELETE /api/admin/posts/{post}
    public function destroy(Post $post): JsonResponse
    {
        $post->delete();

        return response()->json(['message' => 'تم مسح البوست.']);
    }
}
