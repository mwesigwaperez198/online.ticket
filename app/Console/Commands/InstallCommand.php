<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    /**
     * The password the demo seeder falls back to. It is replaced with a random
     * password on a real deployment so the platform never ships with it.
     */
    private const DEFAULT_PASSWORD = 'password';

    protected $signature = 'ticketing:install {--force : Re-run the demo seed even when the database already has users}';

    protected $description = 'Seed the platform on first boot and provision the administrator account';

    public function handle(): int
    {
        $email = trim((string) config('ticketing.admin.email', ''));
        $password = (string) config('ticketing.admin.password', '');

        if (User::query()->exists() && ! $this->option('force')) {
            $this->components->info('Users already exist — skipping the demo seed so live sales data is preserved.');
        } else {
            $this->call('db:seed', ['--force' => true]);
            $this->components->info('Demo accounts, platform settings, the demo event and the demo service were seeded.');
        }

        $this->provisionAdministrator($email, $password);

        return self::SUCCESS;
    }

    private function provisionAdministrator(string $email, string $password): void
    {
        if ($email === '') {
            $this->components->warn('ADMIN_EMAIL is empty — no administrator account could be provisioned.');

            return;
        }

        $admin = User::query()->where('email', $email)->first();

        if (! $admin instanceof User) {
            $secret = $password !== '' ? $password : Str::password(16);

            $admin = User::query()->create([
                'name' => 'Platform Admin',
                'email' => $email,
                'phone' => '0240000001',
                'password' => $secret,
                'role' => 'admin',
                'status' => 'active',
            ]);

            $admin->forceFill(['email_verified_at' => now()])->save();

            $this->components->info(sprintf('Administrator %s created.', $email));

            if ($password === '') {
                $this->components->warn(sprintf(
                    'ADMIN_PASSWORD is not set. The administrator password for %s is: %s',
                    $email,
                    $secret,
                ));
            }

            return;
        }

        if ($password !== '' && ! Hash::check($password, (string) $admin->password)) {
            $admin->forceFill(['password' => $password])->save();

            $this->components->info('Administrator password synchronised with ADMIN_PASSWORD.');

            return;
        }

        if ($password === '' && Hash::check(self::DEFAULT_PASSWORD, (string) $admin->password)) {
            $secret = Str::password(16);
            $admin->forceFill(['password' => $secret])->save();

            $this->components->warn(sprintf(
                'ADMIN_PASSWORD is not set. The administrator password for %s was randomised to: %s',
                $email,
                $secret,
            ));
        }
    }
}
