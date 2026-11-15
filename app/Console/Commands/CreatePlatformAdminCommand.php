<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'stockora:platform-admin:create
        {--name= : Display name for the Platform Super Admin}
        {--email= : Unique email address for the Platform Super Admin}
        {--password= : Password (omit to use STOCKORA_PLATFORM_ADMIN_PASSWORD env or interactive prompt)}
        {--inactive : Create account in inactive state}
        {--update : Update existing admin with matching email instead of failing}';

    protected $description = 'Create or update a Stockora Platform Super Admin account safely via CLI without hardcoded credentials.';

    public function handle(): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Platform Super Admin full name')));
        $email = strtolower(trim((string) ($this->option('email')
            ?: config('stockora.platform_bootstrap.email')
            ?: $this->ask('Platform Super Admin email'))));

        $password = (string) ($this->option('password') ?: config('stockora.platform_bootstrap.password', ''));
        if ($password === '') {
            if (!$this->input->isInteractive()) {
                $this->error('Password is required in non-interactive mode via --password or STOCKORA_PLATFORM_ADMIN_PASSWORD.');

                return self::FAILURE;
            }
            $password = (string) $this->secret('Platform Super Admin password (min 10 chars, letters & numbers)');
        }

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(10)->letters()->numbers()],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existing = PlatformAdmin::where('email', $email)->first();
        if ($existing && !$this->option('update')) {
            $this->error("Platform Super Admin with email [{$email}] already exists. Pass --update to update password/status.");

            return self::FAILURE;
        }

        $isActive = !$this->option('inactive');

        if ($existing) {
            $existing->update([
                'name' => $name,
                'password' => Hash::make($password),
                'is_active' => $isActive,
            ]);
            $this->info("Updated Platform Super Admin #{$existing->id} ({$existing->email}) active=" . ($isActive ? 'true' : 'false'));

            return self::SUCCESS;
        }

        $admin = PlatformAdmin::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => $isActive,
        ]);

        $this->info("Created Platform Super Admin #{$admin->id} ({$admin->email}) active=" . ($isActive ? 'true' : 'false'));

        return self::SUCCESS;
    }
}
