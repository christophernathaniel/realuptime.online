<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_results', function (Blueprint $table) {
            $table->index(['status', 'checked_at', 'id'], 'check_results_prune_idx');
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->index(['created_at', 'id'], 'notification_logs_prune_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex('notification_logs_prune_idx');
        });

        Schema::table('check_results', function (Blueprint $table) {
            $table->dropIndex('check_results_prune_idx');
        });
    }
};
