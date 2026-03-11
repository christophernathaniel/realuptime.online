<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->index(
                ['status', 'next_check_at', 'check_claimed_at', 'id'],
                'monitors_dispatch_claim_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->dropIndex('monitors_dispatch_claim_idx');
        });
    }
};
