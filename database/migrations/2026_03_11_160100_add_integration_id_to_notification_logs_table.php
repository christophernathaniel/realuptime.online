<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->foreignId('integration_id')
                ->nullable()
                ->after('notification_contact_id')
                ->constrained('workspace_integrations')
                ->nullOnDelete();

            $table->index(['integration_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('integration_id');
            $table->dropIndex(['integration_id', 'created_at']);
        });
    }
};
