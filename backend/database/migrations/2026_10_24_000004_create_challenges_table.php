<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('text');
            $table->enum('type', ['breathing', 'walking', 'reading']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
