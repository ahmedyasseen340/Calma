<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->text('reply')->nullable();
            $table->boolean('is_danger')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['patient_id', 'created_at']);
            $table->index('is_danger');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
