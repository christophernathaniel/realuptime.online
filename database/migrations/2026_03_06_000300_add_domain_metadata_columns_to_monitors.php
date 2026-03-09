<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->timestamp('domain_expires_at')->nullable()->after('ssl_expires_at');
            $table->string('domain_registrar')->nullable()->after('domain_expires_at');
            $table->timestamp('domain_checked_at')->nullable()->after('domain_registrar');
            $table->timestamp('ssl_checked_at')->nullable()->after('ssl_issuer');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn([
                'domain_expires_at',
                'domain_registrar',
                'domain_checked_at',
                'ssl_checked_at',
            ]);
        });
    }
};
