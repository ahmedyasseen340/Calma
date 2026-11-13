<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('certificate_img');   // required by the API for new doctors
            $table->string('clinic_url')->nullable()->after('phone');
            $table->string('photo')->nullable()->after('clinic_url');             // required by the API for new doctors
            $table->string('bio', 300)->nullable()->after('photo');               // max 300 characters
        });

        // Keep what doctors who already registered entered in contact_links.
        DB::table('doctor_profiles')->whereNotNull('contact_links')->orderBy('id')->each(function ($row) {
            $links = json_decode($row->contact_links, true) ?: [];

            DB::table('doctor_profiles')->where('id', $row->id)->update([
                'phone' => $links['phones'][0] ?? null,
                'clinic_url' => $links['clinic_url'] ?? null,
            ]);
        });

        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->dropColumn('contact_links');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->json('contact_links')->nullable()->after('certificate_img');
        });

        DB::table('doctor_profiles')->orderBy('id')->each(function ($row) {
            DB::table('doctor_profiles')->where('id', $row->id)->update([
                'contact_links' => json_encode([
                    'phones' => $row->phone ? [$row->phone] : [],
                    'clinic_url' => $row->clinic_url,
                ]),
            ]);
        });

        Schema::table('doctor_profiles', function (Blueprint $table) {
            $table->dropColumn(['phone', 'clinic_url', 'photo', 'bio']);
        });
    }
};
