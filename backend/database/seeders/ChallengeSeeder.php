<?php

namespace Database\Seeders;

use App\Models\Challenge;
use Illuminate\Database\Seeder;

class ChallengeSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['text' => 'تنفس بعمق لمدة 5 دقائق (شهيق 4 ثواني، زفير 6 ثواني)', 'type' => 'breathing'],
            ['text' => 'تمرين تنفس الصندوق: 4 ثواني شهيق، 4 حبس، 4 زفير، 4 حبس', 'type' => 'breathing'],
            ['text' => 'امشي 15 دقيقة في مكان هادي', 'type' => 'walking'],
            ['text' => 'خد مشوار قصير برا البيت من غير موبايل', 'type' => 'walking'],
            ['text' => 'اقرا 10 صفحات من كتاب بتحبه', 'type' => 'reading'],
            ['text' => 'اقرا مقال عن النوم الصحي', 'type' => 'reading'],
        ];

        foreach ($items as $item) {
            Challenge::firstOrCreate(['text' => $item['text']], $item);
        }
    }
}
