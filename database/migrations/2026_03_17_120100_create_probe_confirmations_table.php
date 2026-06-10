<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('probe_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_check_result_id')->nullable()->constrained('check_results')->nullOnDelete();
            $table->string('kind');
            $table->string('status')->default('pending');
            $table->string('primary_region');
            $table->json('confirmation_regions')->nullable();
            $table->json('results')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'kind', 'status']);
            $table->index(['incident_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('probe_confirmations');
    }
};
