<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('audio_path')->nullable()->after('is_danger');   // private file, null for text messages
            $table->enum('emotion', ['happy', 'sad', 'angry', 'anxious', 'neutral'])->nullable()->after('audio_path');
            $table->decimal('emotion_score', 4, 3)->nullable()->after('emotion'); // confidence 0.000 to 1.000
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['audio_path', 'emotion', 'emotion_score']);
        });
    }
};
