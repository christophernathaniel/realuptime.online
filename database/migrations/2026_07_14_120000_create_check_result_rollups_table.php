<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_result_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('granularity_seconds');
            $table->timestamp('bucket_started_at');
            $table->timestamp('bucket_ended_at');
            $table->unsignedBigInteger('total_checks');
            $table->unsignedBigInteger('up_checks');
            $table->unsignedBigInteger('down_checks');
            $table->unsignedBigInteger('slow_checks');
            $table->unsignedBigInteger('response_time_samples');
            $table->unsignedBigInteger('response_time_sum_ms');
            $table->unsignedInteger('response_time_min_ms')->nullable();
            $table->unsignedInteger('response_time_max_ms')->nullable();
            $table->timestamp('first_checked_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['monitor_id', 'granularity_seconds', 'bucket_started_at'],
                'check_result_rollups_monitor_granularity_bucket_unique'
            );
            $table->index(
                ['granularity_seconds', 'bucket_started_at', 'id'],
                'check_result_rollups_compaction_idx'
            );
            $table->index(
                ['monitor_id', 'bucket_started_at', 'bucket_ended_at'],
                'check_result_rollups_monitor_window_idx'
            );
        });

        Schema::table('check_results', function (Blueprint $table) {
            $table->dropIndex('check_results_prune_idx');
            $table->index(['checked_at', 'id'], 'check_results_checked_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('check_results', function (Blueprint $table) {
            $table->dropIndex('check_results_checked_id_idx');
            $table->index(['status', 'checked_at', 'id'], 'check_results_prune_idx');
        });

        Schema::dropIfExists('check_result_rollups');
    }
};
