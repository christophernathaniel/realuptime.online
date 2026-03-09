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
        Schema::create('notification_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->boolean('is_primary')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'email']);
        });

        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('status')->default('paused');
            $table->string('target')->nullable();
            $table->string('request_method', 10)->nullable();
            $table->unsignedInteger('interval_seconds')->default(300);
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->unsignedTinyInteger('retry_limit')->default(2);
            $table->boolean('follow_redirects')->default(true);
            $table->json('custom_headers')->nullable();
            $table->string('auth_username')->nullable();
            $table->text('auth_password')->nullable();
            $table->unsignedSmallInteger('expected_status_code')->nullable();
            $table->string('expected_keyword')->nullable();
            $table->string('keyword_match_type')->nullable();
            $table->unsignedTinyInteger('packet_count')->nullable();
            $table->unsignedInteger('latency_threshold_ms')->nullable();
            $table->unsignedSmallInteger('ssl_threshold_days')->nullable();
            $table->unsignedInteger('heartbeat_grace_seconds')->nullable();
            $table->string('region')->default('North America');
            $table->string('heartbeat_token')->nullable()->unique();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_status_changed_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->unsignedInteger('last_response_time_ms')->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->string('last_error_type')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->string('ssl_issuer')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'last_checked_at']);
        });

        Schema::create('monitor_notification_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_contact_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['monitor_id', 'notification_contact_id'], 'monitor_contact_unique');
        });

        Schema::create('check_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('checked_at');
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedSmallInteger('http_status_code')->nullable();
            $table->string('error_type')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('keyword_match')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'checked_at']);
            $table->index(['monitor_id', 'status']);
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('latest_check_result_id')->nullable()->constrained('check_results')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('duration_seconds')->nullable();
            $table->string('reason');
            $table->string('error_type')->nullable();
            $table->unsignedSmallInteger('http_status_code')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'started_at']);
            $table->index(['monitor_id', 'resolved_at']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('notification_contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel')->default('email');
            $table->string('type');
            $table->string('subject');
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'created_at']);
        });

        Schema::create('heartbeat_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestamp('received_at');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('heartbeat_events');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('check_results');
        Schema::dropIfExists('monitor_notification_contact');
        Schema::dropIfExists('monitors');
        Schema::dropIfExists('notification_contacts');
    }
};
