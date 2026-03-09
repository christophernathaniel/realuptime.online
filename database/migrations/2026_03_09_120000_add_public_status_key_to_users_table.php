<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('public_status_key', 24)->nullable()->unique()->after('email');
        });

        User::query()
            ->whereNull('public_status_key')
            ->eachById(function (User $user): void {
                $user->forceFill([
                    'public_status_key' => $this->generatePublicStatusKey(),
                ])->saveQuietly();
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_public_status_key_unique');
            $table->dropColumn('public_status_key');
        });
    }

    private function generatePublicStatusKey(): string
    {
        do {
            $key = collect(range(1, 4))
                ->map(fn (): string => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT))
                ->implode('');
        } while (User::query()->where('public_status_key', $key)->exists());

        return $key;
    }
};
