<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('monitors')
            ->where('interval_seconds', '<', 60)
            ->update([
                'interval_seconds' => 60,
                'admin_interval_override' => false,
            ]);
    }

    public function down(): void
    {
        // Existing intervals cannot be reconstructed safely.
    }
};
