<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->string('accepted_http_statuses', 120)->nullable()->after('expected_status_code');
            $table->unsignedInteger('last_queue_lag_ms')->nullable()->after('last_response_time_ms');
            $table->string('last_probe_region')->nullable()->after('region');
        });

        DB::table('monitors')
            ->where('type', 'http')
            ->orderBy('id')
            ->chunkById(500, function ($monitors): void {
                foreach ($monitors as $monitor) {
                    DB::table('monitors')
                        ->where('id', $monitor->id)
                        ->update([
                            'accepted_http_statuses' => $monitor->expected_status_code
                                ? (string) $monitor->expected_status_code
                                : '200-299',
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn([
                'accepted_http_statuses',
                'last_queue_lag_ms',
                'last_probe_region',
            ]);
        });
    }
};
