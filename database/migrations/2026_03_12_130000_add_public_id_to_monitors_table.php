<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->string('public_id', 26)->nullable()->after('id');
        });

        DB::table('monitors')
            ->select('id')
            ->whereNull('public_id')
            ->orderBy('id')
            ->chunkById(200, function ($monitors): void {
                foreach ($monitors as $monitor) {
                    DB::table('monitors')
                        ->where('id', $monitor->id)
                        ->update(['public_id' => (string) Str::ulid()]);
                }
            });

        Schema::table('monitors', function (Blueprint $table): void {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
