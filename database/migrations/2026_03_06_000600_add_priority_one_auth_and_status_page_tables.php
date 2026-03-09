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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('password_login_enabled')->default(true)->after('password');
        });

        Schema::create('connected_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_id');
            $table->string('provider_email')->nullable();
            $table->string('provider_name')->nullable();
            $table->string('avatar_url')->nullable();
            $table->text('token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->unique(['provider', 'provider_id']);
        });

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('last_path')->nullable();
            $table->timestamp('last_active_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'session_id']);
            $table->index(['user_id', 'last_active_at']);
        });

        Schema::create('status_page_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('status', 32)->default('investigating');
            $table->string('impact', 32)->default('minor');
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status_page_id', 'status']);
            $table->index(['status_page_id', 'resolved_at']);
        });

        Schema::create('status_page_incident_monitor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_page_incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['status_page_incident_id', 'monitor_id'], 'status_page_incident_monitor_unique');
        });

        Schema::create('status_page_incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_page_incident_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->text('message');
            $table->timestamps();

            $table->index(['status_page_incident_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_page_incident_updates');
        Schema::dropIfExists('status_page_incident_monitor');
        Schema::dropIfExists('status_page_incidents');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('connected_accounts');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_login_enabled');
        });
    }
};
