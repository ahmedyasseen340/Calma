<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['anxiety', 'sleep', 'depression'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
