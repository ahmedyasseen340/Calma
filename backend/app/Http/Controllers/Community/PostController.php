<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    /** Author info shown with each post (no email, no private documents). */
    private const WITH_AUTHOR = [
        'user:id,first_name,last_name,role',
        'user.doctorProfile:id,user_id,specialization,phone,clinic_url,photo,bio',
    ];

    // GET /api/posts : newest first, paginated (patients and doctors)
    public function index(): JsonResponse
    {
        return response()->json(
            Post::with(self::WITH_AUTHOR)->latest('id')->paginate(15)
        );
    }

    // GET /api/posts/{post}
    public function show(Post $post): JsonResponse
    {
        return response()->json(['post' => $post->load(self::WITH_AUTHOR)]);
    }

    // POST /api/posts   body: { content }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $post = $request->user()->posts()->create($data);

        return response()->json(['post' => $post->load(self::WITH_AUTHOR)], 201);
    }

    // POST /api/posts/{post}   body: { content }   (only the author)
    public function update(Request $request, Post $post): JsonResponse
    {
        $this->authorizeAuthor($request, $post);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $post->update($data);

        return response()->json(['post' => $post->load(self::WITH_AUTHOR)]);
    }

    // DELETE /api/posts/{post}   (only the author)
    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->authorizeAuthor($request, $post);

        $post->delete();

        return response()->json(['message' => 'تم مسح البوست.']);
    }

    private function authorizeAuthor(Request $request, Post $post): void
    {
        abort_unless($post->user_id === $request->user()->id, 403, 'تقدر تعدّل أو تمسح بوستاتك إنت بس.');
    }
}
