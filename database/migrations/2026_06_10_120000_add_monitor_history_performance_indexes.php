<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('check_results', function (Blueprint $table) {
            $table->index(['monitor_id', 'checked_at', 'response_time_ms'], 'check_results_monitor_checked_response_idx');
            $table->index(['monitor_id', 'response_time_ms', 'checked_at'], 'check_results_monitor_response_checked_idx');
        });
    }

    public function down(): void
    {
        Schema::table('check_results', function (Blueprint $table) {
            $table->dropIndex('check_results_monitor_response_checked_idx');
            $table->dropIndex('check_results_monitor_checked_response_idx');
        });
    }
};
