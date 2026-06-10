<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('support_plan_extension', 32)->nullable()->after('admin_plan_assigned_at');
            $table->foreignId('support_plan_granted_by')->nullable()->after('support_plan_extension')->constrained('users')->nullOnDelete();
            $table->timestamp('support_plan_granted_at')->nullable()->after('support_plan_granted_by');
            $table->timestamp('support_plan_expires_at')->nullable()->after('support_plan_granted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('support_plan_granted_by');
            $table->dropColumn([
                'support_plan_extension',
                'support_plan_granted_at',
                'support_plan_expires_at',
            ]);
        });
    }
};
