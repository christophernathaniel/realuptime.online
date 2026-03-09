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
        Schema::create('status_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('headline')->nullable();
            $table->text('description')->nullable();
            $table->boolean('published')->default(true);
            $table->string('custom_domain')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'published']);
        });

        Schema::create('status_page_monitor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['status_page_id', 'monitor_id']);
        });

        Schema::create('maintenance_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status')->default('scheduled');
            $table->boolean('notify_contacts')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('maintenance_window_monitor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_window_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['maintenance_window_id', 'monitor_id'], 'maintenance_window_monitor_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_window_monitor');
        Schema::dropIfExists('maintenance_windows');
        Schema::dropIfExists('status_page_monitor');
        Schema::dropIfExists('status_pages');
    }
};
