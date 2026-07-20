<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->longText('payload')->nullable()->change();
        });

        $this->transformPayloads(encrypt: true);
    }

    public function down(): void
    {
        $this->transformPayloads(encrypt: false);

        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->json('payload')->nullable()->change();
        });
    }

    protected function transformPayloads(bool $encrypt): void
    {
        DB::table('notification_logs')
            ->select(['id', 'payload'])
            ->whereNotNull('payload')
            ->orderBy('id')
            ->chunkById(250, function ($logs) use ($encrypt): void {
                foreach ($logs as $log) {
                    $payload = (string) $log->payload;

                    if ($payload === '') {
                        continue;
                    }

                    DB::table('notification_logs')->where('id', $log->id)->update([
                        'payload' => $encrypt
                            ? $this->encryptAndSanitize($payload)
                            : ($this->tryDecrypt($payload) ?? $payload),
                    ]);
                }
            });
    }

    protected function encryptAndSanitize(string $payload): string
    {
        $plainText = $this->tryDecrypt($payload) ?? $payload;
        $decoded = json_decode($plainText, true, 512, JSON_THROW_ON_ERROR);

        if (is_array($decoded) && isset($decoded['url']) && is_string($decoded['url'])) {
            $decoded['url_host'] = parse_url($decoded['url'], PHP_URL_HOST) ?: 'Configured webhook';
            unset($decoded['url']);
            $plainText = json_encode($decoded, JSON_THROW_ON_ERROR);
        }

        return Crypt::encryptString($plainText);
    }

    protected function tryDecrypt(string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
};
