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
        Schema::table('monitors', function (Blueprint $table): void {
            $table->longText('custom_headers')->nullable()->change();
            $table->longText('synthetic_steps')->nullable()->change();
            $table->longText('downtime_webhook_urls')->nullable()->change();
        });

        $this->transformMonitorData(encrypt: true);
        $this->transformConnectedAccountTokens(encrypt: true);
    }

    public function down(): void
    {
        $this->transformConnectedAccountTokens(encrypt: false);
        $this->transformMonitorData(encrypt: false);

        Schema::table('monitors', function (Blueprint $table): void {
            $table->json('custom_headers')->nullable()->change();
            $table->json('synthetic_steps')->nullable()->change();
            $table->json('downtime_webhook_urls')->nullable()->change();
        });
    }

    protected function transformMonitorData(bool $encrypt): void
    {
        DB::table('monitors')
            ->select(['id', 'custom_headers', 'synthetic_steps', 'downtime_webhook_urls'])
            ->orderBy('id')
            ->chunkById(250, function ($monitors) use ($encrypt): void {
                foreach ($monitors as $monitor) {
                    $values = [];

                    foreach (['custom_headers', 'synthetic_steps', 'downtime_webhook_urls'] as $column) {
                        $value = $monitor->{$column};

                        if ($value === null || $value === '') {
                            continue;
                        }

                        $values[$column] = $encrypt
                            ? $this->encryptJsonValue((string) $value)
                            : $this->decryptValue((string) $value);
                    }

                    if ($values !== []) {
                        DB::table('monitors')->where('id', $monitor->id)->update($values);
                    }
                }
            });
    }

    protected function transformConnectedAccountTokens(bool $encrypt): void
    {
        DB::table('connected_accounts')
            ->select(['id', 'token', 'refresh_token'])
            ->orderBy('id')
            ->chunkById(250, function ($accounts) use ($encrypt): void {
                foreach ($accounts as $account) {
                    $values = [];

                    foreach (['token', 'refresh_token'] as $column) {
                        $value = $account->{$column};

                        if ($value === null || $value === '') {
                            continue;
                        }

                        $values[$column] = $encrypt
                            ? $this->encryptValue((string) $value)
                            : $this->decryptValue((string) $value);
                    }

                    if ($values !== []) {
                        DB::table('connected_accounts')->where('id', $account->id)->update($values);
                    }
                }
            });
    }

    protected function encryptJsonValue(string $value): string
    {
        $decrypted = $this->tryDecrypt($value);

        if ($decrypted !== null) {
            return $value;
        }

        json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        return Crypt::encryptString($value);
    }

    protected function encryptValue(string $value): string
    {
        return $this->tryDecrypt($value) !== null ? $value : Crypt::encryptString($value);
    }

    protected function decryptValue(string $value): string
    {
        return $this->tryDecrypt($value) ?? $value;
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
