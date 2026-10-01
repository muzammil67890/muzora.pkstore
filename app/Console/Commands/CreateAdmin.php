<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Create the first or an additional MUZORA.PK administrator';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Admin name'));
        $email = strtolower(trim((string) $this->ask('Admin email')));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email:rfc', 'max:255', 'unique:admins,email']]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = (string) $this->secret('Admin password (minimum 12 characters)');
        $passwordConfirmation = (string) $this->secret('Confirm admin password');
        $passwordValidator = Validator::make(
            ['password' => $password, 'password_confirmation' => $passwordConfirmation],
            ['password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()]]
        );

        if ($passwordValidator->fails()) {
            foreach ($passwordValidator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        Admin::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $this->info("Administrator {$email} created.");

        return self::SUCCESS;
    }
}
