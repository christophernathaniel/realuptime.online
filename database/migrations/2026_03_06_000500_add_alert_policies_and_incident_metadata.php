<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->unsignedTinyInteger('degraded_consecutive_checks')->default(3)->after('latency_threshold_ms');
            $table->unsignedInteger('critical_alert_after_minutes')->default(30)->after('degraded_consecutive_checks');
            $table->unsignedSmallInteger('domain_threshold_days')->default(30)->after('ssl_threshold_days');
        });

        Schema::table('incidents', function (Blueprint $table) {
            $table->foreignId('first_check_result_id')->nullable()->after('monitor_id')->constrained('check_results')->nullOnDelete();
            $table->foreignId('last_good_check_result_id')->nullable()->after('first_check_result_id')->constrained('check_results')->nullOnDelete();
            $table->string('type')->default('downtime')->after('duration_seconds');
            $table->string('severity')->default('major')->after('type');
            $table->text('operator_notes')->nullable()->after('meta');
            $table->text('root_cause_summary')->nullable()->after('operator_notes');
            $table->timestamp('critical_alert_sent_at')->nullable()->after('root_cause_summary');

            $table->index(['monitor_id', 'type', 'resolved_at'], 'incidents_monitor_type_resolved_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropIndex('incidents_monitor_type_resolved_index');
            $table->dropConstrainedForeignId('first_check_result_id');
            $table->dropConstrainedForeignId('last_good_check_result_id');
            $table->dropColumn([
                'type',
                'severity',
                'operator_notes',
                'root_cause_summary',
                'critical_alert_sent_at',
            ]);
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn([
                'degraded_consecutive_checks',
                'critical_alert_after_minutes',
                'domain_threshold_days',
            ]);
        });
    }
};
