<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Community posts.
 *  - A DOCTOR post shows the doctor (name, specialization, contact, photo, bio).
 *  - A PATIENT post is ANONYMOUS: no name and no id of the author is ever returned to other users.
 *    The author is only kept inside the database (posts.user_id) so the admin can moderate and the
 *    author can edit or delete their own post (`is_mine` tells the app which posts are the user's).
 */
class PostController extends Controller
{
    private const WITH_AUTHOR = [
        'user:id,first_name,last_name,role',
        'user.doctorProfile:id,user_id,specialization,phone,clinic_url,photo,bio',
    ];

    // GET /api/posts?type=doctor|patient : newest first, paginated
    public function index(Request $request): JsonResponse
    {
        $request->validate(['type' => ['nullable', 'in:doctor,patient']]);

        $posts = Post::with(self::WITH_AUTHOR)
            ->when($request->type, fn ($q, $t) => $q->whereHas('user', fn ($u) => $u->where('role', $t)))
            ->latest('id')
            ->paginate(15)
            ->through(fn (Post $p) => $this->present($p, $request));

        return response()->json($posts);
    }

    // GET /api/posts/{post}
    public function show(Request $request, Post $post): JsonResponse
    {
        return response()->json(['post' => $this->present($post->load(self::WITH_AUTHOR), $request)]);
    }

    // POST /api/posts   body: { content }
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $post = $request->user()->posts()->create($data);

        return response()->json(['post' => $this->present($post->load(self::WITH_AUTHOR), $request)], 201);
    }

    // POST /api/posts/{post}   body: { content }   (only the author)
    public function update(Request $request, Post $post): JsonResponse
    {
        $this->authorizeAuthor($request, $post);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $post->update($data);

        return response()->json(['post' => $this->present($post->load(self::WITH_AUTHOR), $request)]);
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

    private function present(Post $post, Request $request): array
    {
        $author = $post->user;
        $isDoctor = $author && $author->role === 'doctor';
        $profile = $isDoctor ? $author->doctorProfile : null;

        return [
            'id' => $post->id,
            'type' => $isDoctor ? 'doctor' : 'patient',
            'is_anonymous' => ! $isDoctor,
            'is_mine' => $post->user_id === $request->user()->id,
            'content' => $post->content,
            'created_at' => $post->created_at,
            // patient posts: null, always
            'author' => $isDoctor && $profile ? [
                'first_name' => $author->first_name,
                'last_name' => $author->last_name,
                'specialization' => $profile->specialization,
                'phone' => $profile->phone,
                'clinic_url' => $profile->clinic_url,
                'bio' => $profile->bio,
                'photo_url' => $profile->photo_url,
            ] : null,
        ];
    }
}
