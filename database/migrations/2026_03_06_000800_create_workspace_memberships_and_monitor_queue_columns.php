<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invited_email');
            $table->string('token')->unique();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['owner_user_id', 'invited_email']);
            $table->index(['member_user_id', 'accepted_at']);
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->timestamp('next_check_at')->nullable()->after('last_checked_at');
            $table->timestamp('last_dispatched_at')->nullable()->after('next_check_at');
            $table->timestamp('check_claimed_at')->nullable()->after('last_dispatched_at');
            $table->string('check_claim_token', 40)->nullable()->after('check_claimed_at');

            $table->index(['status', 'next_check_at']);
            $table->index('check_claimed_at');
            $table->index('check_claim_token');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropIndex(['status', 'next_check_at']);
            $table->dropIndex(['check_claimed_at']);
            $table->dropIndex(['check_claim_token']);
            $table->dropColumn([
                'next_check_at',
                'last_dispatched_at',
                'check_claimed_at',
                'check_claim_token',
            ]);
        });

        Schema::dropIfExists('workspace_memberships');
    }
};
