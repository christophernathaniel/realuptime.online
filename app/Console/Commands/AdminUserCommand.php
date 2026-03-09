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
        $email = Str::lower((string) $this->argument('email'));
        $grantAdmin = ! $this->option('revoke');

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user) {
            $this->error(sprintf('No user found for %s.', $email));

            return self::FAILURE;
        }

        if (! $grantAdmin && $user->is_admin && User::query()->where('is_admin', true)->whereKeyNot($user->id)->doesntExist()) {
            $this->error('At least one admin user must remain.');

            return self::FAILURE;
        }

        $user->forceFill([
            'is_admin' => $grantAdmin,
        ])->save();

        $this->info(sprintf(
            '%s %s.',
            $user->email,
            $grantAdmin ? 'now has admin access' : 'no longer has admin access',
        ));

        return self::SUCCESS;
    }
}
