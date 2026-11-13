<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use RefreshDatabase;

    private function actAs(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_admin_creates_article_with_categories_and_cover(): void
    {
        Storage::fake('public');
        $this->actAs('admin');
        $sleep = Category::create(['name' => 'sleep']);

        $response = $this->post('/api/admin/articles', [
            'title' => 'نوم صحي',
            'summary' => 'نصائح للنوم',
            'url' => 'https://example.com/sleep',
            'cover' => UploadedFile::fake()->image('cover.jpg'),
            'category_ids' => [$sleep->id],
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('article.categories.0.name', 'sleep');
        Storage::disk('public')->assertExists(Article::first()->cover);
    }

    public function test_patient_can_read_and_filter_articles_but_not_create(): void
    {
        $sleep = Category::create(['name' => 'sleep']);
        $anxiety = Category::create(['name' => 'anxiety']);
        Article::create(['title' => 'A', 'summary' => 's', 'url' => 'https://a.test'])->categories()->sync([$sleep->id]);
        Article::create(['title' => 'B', 'summary' => 's', 'url' => 'https://b.test'])->categories()->sync([$anxiety->id]);

        $this->actAs('patient');

        $this->getJson('/api/articles')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/articles?category_id={$sleep->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/admin/articles', ['title' => 'x'])->assertForbidden();
    }

    public function test_category_name_must_be_unique(): void
    {
        $this->actAs('admin');

        $this->postJson('/api/admin/categories', ['name' => 'sleep'])->assertCreated();
        $this->postJson('/api/admin/categories', ['name' => 'sleep'])->assertUnprocessable();
    }

    public function test_admin_uploads_audio_and_patient_lists_it(): void
    {
        Storage::fake('public');
        $this->actAs('admin');

        $this->post('/api/admin/audios', [
            'title' => 'تأمل 5 دقائق',
            'file' => UploadedFile::fake()->create('calm.mp3', 500, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->actAs('patient');

        $this->getJson('/api/audios')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_audio_rejects_non_audio_files(): void
    {
        Storage::fake('public');
        $this->actAs('admin');

        $this->post('/api/admin/audios', [
            'title' => 'x',
            'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }
}
