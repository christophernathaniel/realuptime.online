<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('admin_plan_override', 32)->nullable()->after('is_admin');
            $table->foreignId('admin_plan_assigned_by')->nullable()->after('admin_plan_override')->constrained('users')->nullOnDelete();
            $table->timestamp('admin_plan_assigned_at')->nullable()->after('admin_plan_assigned_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_plan_assigned_by');
            $table->dropColumn([
                'admin_plan_override',
                'admin_plan_assigned_at',
            ]);
        });
    }
};
