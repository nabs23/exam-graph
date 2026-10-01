<?php

namespace App\Console\Commands;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('app:create-user {name} {email} {password} {--admin : Grant the user administrator access}')]
#[Description('Create a user account.')]
class CreateUser extends Command
{
    use ProfileValidationRules;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $profileValidator = Validator::make([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
        ], $this->profileRules());

        if ($profileValidator->fails()) {
            foreach ($profileValidator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $passwordValidator = Validator::make([
            'password' => $this->argument('password'),
        ], [
            'password' => ['required', 'string', Password::default()],
        ]);

        if ($passwordValidator->fails()) {
            foreach ($passwordValidator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $profileValidator->validated()['name'],
            'email' => $profileValidator->validated()['email'],
            'password' => $passwordValidator->validated()['password'],
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
            'is_admin' => (bool) $this->option('admin'),
        ])->save();

        $this->info("User {$user->email} created successfully.");

        return self::SUCCESS;
    }
}
