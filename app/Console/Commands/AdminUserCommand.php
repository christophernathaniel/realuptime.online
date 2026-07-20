<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AdminUserCommand extends Command
{
    protected $signature = 'realuptime:admin-user
        {email : The email address to update}
        {--revoke : Remove admin access instead of granting it}';

    protected $description = 'Grant or revoke RealUptime platform admin access for a user account';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $mainAdminEmail = Str::lower(trim((string) config('realuptime.admin.main_admin_email')));
        $grantAdmin = ! $this->option('revoke');

        if ($mainAdminEmail === '') {
            $this->error('Set REALUPTIME_MAIN_ADMIN_EMAIL before granting platform admin access.');

            return self::FAILURE;
        }

        if ($email !== $mainAdminEmail) {
            $this->error(sprintf('%s is not the configured REALUPTIME_MAIN_ADMIN_EMAIL.', $email));

            return self::FAILURE;
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user) {
            $this->error(sprintf('No user found for %s.', $email));

            return self::FAILURE;
        }

        if ($grantAdmin) {
            User::query()
                ->whereKeyNot($user->id)
                ->where('is_admin', true)
                ->update(['is_admin' => false]);
        }

        $user->forceFill([
            'is_admin' => $grantAdmin,
        ])->save();

        $this->info(sprintf(
            '%s %s.',
            $user->email,
            $grantAdmin ? 'is now the sole main admin' : 'no longer has main admin access',
        ));

        return self::SUCCESS;
    }
}
