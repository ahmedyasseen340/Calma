<?php

use App\Http\Controllers\Admin\DoctorApprovalController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\DoctorResubmitController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Content\ArticleController;
use App\Http\Controllers\Content\AudioController;
use App\Http\Controllers\Community\PostController;
use App\Http\Controllers\Content\CategoryController;
use App\Http\Controllers\Patient\ChallengeController;
use App\Http\Controllers\Patient\ChatController;
use App\Http\Controllers\Patient\MoodLogController;
use App\Http\Controllers\Profile\AccountController;
use App\Http\Controllers\Profile\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:5,1');
    Route::post('verify-reset-code', [PasswordResetController::class, 'verifyCode'])->middleware('throttle:10,1');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1');
    Route::post('doctor/resubmit', DoctorResubmitController::class)->middleware('throttle:5,1');   // rejected doctor sends the data again

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('change-password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1');
        Route::post('delete-account', [AccountController::class, 'destroy'])->middleware(['role:patient,doctor', 'throttle:5,1']);
    });
});

// ---- Admin: change doctor_profiles.status ----
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('doctors', [DoctorApprovalController::class, 'index']);
    Route::post('doctors/{doctor}/approve', [DoctorApprovalController::class, 'approve']);
    Route::post('doctors/{doctor}/reject', [DoctorApprovalController::class, 'reject']);

    // Content management (updates use POST so multipart file uploads work)
    Route::post('categories', [CategoryController::class, 'store']);
    Route::post('categories/{category}', [CategoryController::class, 'update']);
    Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

    Route::post('articles', [ArticleController::class, 'store']);
    Route::post('articles/{article}', [ArticleController::class, 'update']);
    Route::delete('articles/{article}', [ArticleController::class, 'destroy']);

    Route::post('audios', [AudioController::class, 'store']);
    Route::post('audios/{audio}', [AudioController::class, 'update']);
    Route::delete('audios/{audio}', [AudioController::class, 'destroy']);
});

// ---- Content: read-only for any logged-in user ----
Route::middleware('auth:sanctum')->group(function () {
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('articles', [ArticleController::class, 'index']);
    Route::get('articles/{article}', [ArticleController::class, 'show']);
    Route::get('audios', [AudioController::class, 'index']);
    Route::get('audios/{audio}', [AudioController::class, 'show']);
});

// ---- Patient ----
Route::middleware(['auth:sanctum', 'role:patient'])->prefix('patient')->group(function () {
    Route::get('chat', [ChatController::class, 'index']);
    Route::post('chat', [ChatController::class, 'send'])->middleware('throttle:20,1');
    Route::post('chat/voice', [ChatController::class, 'sendVoice'])->middleware('throttle:10,1');
    Route::get('chat/{message}/audio', [ChatController::class, 'audio']);

    Route::get('mood-logs', [MoodLogController::class, 'index']);
    Route::post('mood-logs', [MoodLogController::class, 'store']);

    Route::get('challenges', [ChallengeController::class, 'index']);
    Route::post('challenges/{challenge}/complete', [ChallengeController::class, 'complete']);

    Route::post('profile', [ProfileController::class, 'updatePatient']);
});

// ---- Doctor: edit own profile (POST so a new photo can be uploaded) ----
Route::middleware(['auth:sanctum', 'role:doctor'])->prefix('doctor')->group(function () {
    Route::post('profile', [ProfileController::class, 'updateDoctor']);
});

// ---- Posts: patients and doctors can write and read; each edits or deletes only their own ----
Route::middleware(['auth:sanctum', 'role:patient,doctor'])->prefix('posts')->group(function () {
    Route::get('/', [PostController::class, 'index']);
    Route::get('{post}', [PostController::class, 'show']);
    Route::post('/', [PostController::class, 'store'])->middleware('throttle:20,1');
    Route::post('{post}', [PostController::class, 'update'])->middleware('throttle:20,1');   // edit my post
    Route::delete('{post}', [PostController::class, 'destroy']);                              // delete my post
});
