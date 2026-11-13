<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('specialization');
            $table->date('dob');
            $table->string('id_card_img');
            $table->string('certificate_img');
            $table->json('contact_links')->nullable(); // phones, clinic URL
            $table->enum('status', ['pending', 'active', 'rejected'])->default('pending');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_profiles');
    }
};
