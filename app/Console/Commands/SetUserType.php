<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Bootstrap the first super admin from the server shell:
 *   php artisan user:type you@example.com super_admin
 */
#[Signature('user:type {email} {type=super_admin}')]
#[Description('Set a user\'s type (user, admin, super_admin, or any custom type).')]
class SetUserType extends Command
{
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        $type = UserType::where('slug', $this->argument('type'))->first();

        if (! $user || ! $type) {
            $this->error(! $user ? 'No user with that email.' : 'Unknown type. Known: '.UserType::pluck('slug')->implode(', '));

            return self::FAILURE;
        }

        $user->user_type_id = $type->id;
        $user->save();

        $this->info("{$user->email} is now {$type->name}.");

        return self::SUCCESS;
    }
}
