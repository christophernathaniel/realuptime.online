<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table): void {
            $table->index(['resolved_at', 'monitor_id'], 'incidents_resolved_monitor_idx');
        });

        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->index(['created_at', 'monitor_id'], 'notification_logs_created_monitor_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->dropIndex('notification_logs_created_monitor_idx');
        });

        Schema::table('incidents', function (Blueprint $table): void {
            $table->dropIndex('incidents_resolved_monitor_idx');
        });
    }
};
