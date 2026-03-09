<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name'], 'capabilities_user_name_unique');
            $table->unique(['user_id', 'slug'], 'capabilities_user_slug_unique');
        });

        Schema::create('capability_monitor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capability_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['capability_id', 'monitor_id'], 'capability_monitor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capability_monitor');
        Schema::dropIfExists('capabilities');
    }
};
