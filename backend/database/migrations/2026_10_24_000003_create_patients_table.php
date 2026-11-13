<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('dob'); // age must be over 16: checked at registration (it depends on today's date)
            $table->enum('gender', ['male', 'female']);
            $table->enum('marital_status', ['single', 'married']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
