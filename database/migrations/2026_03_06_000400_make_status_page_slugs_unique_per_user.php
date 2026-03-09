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
        Schema::table('status_pages', function (Blueprint $table) {
            $table->dropUnique('status_pages_slug_unique');
            $table->unique(['user_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('status_pages', function (Blueprint $table) {
            $table->dropUnique('status_pages_user_id_slug_unique');
            $table->unique('slug');
        });
    }
};
