<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * A login for the PromptEdit reviewers: "admin" type, so they can open the
 * report and every read-only table, but can't change keys or user types
 * (super admin only). The password is generated and shown once, never stored
 * anywhere readable.
 */
#[Signature('app:create-reviewer {email} {--name=PromptEdit reviewer}')]
#[Description('Create (or reset) a read-only admin login for the assessment reviewers.')]
class CreateReviewer extends Command
{
    public function handle(): int
    {
        $password = Str::password(20, symbols: false);

        $user = User::firstOrNew(['email' => $this->argument('email')]);
        $user->name = (string) $this->option('name');
        $user->password = $password;
        $user->email_verified_at ??= now();
        $user->user_type_id = UserType::where('slug', UserType::ADMIN)->value('id');
        $user->save();

        $this->info("Reviewer login ready (admin, read-only): {$user->email}");
        $this->line("Password (shown once): {$password}");
        $this->line('Admin: '.rtrim((string) config('app.url'), '/').'/admin');

        return self::SUCCESS;
    }
}
