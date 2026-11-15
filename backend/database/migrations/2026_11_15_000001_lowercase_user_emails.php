<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Two accounts that differ only by letter case would clash after this. List them first.
        $clashes = DB::table('users')
            ->selectRaw('LOWER(email) as e, COUNT(*) as n')
            ->groupByRaw('LOWER(email)')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('e');

        if ($clashes->isNotEmpty()) {
            throw new RuntimeException(
                'These emails exist more than once with different letter case, merge or delete them first: '.$clashes->implode(', ')
            );
        }

        DB::table('users')->update(['email' => DB::raw('LOWER(email)')]);
    }

    public function down(): void
    {
        // the old letter case cannot be restored
    }
};
