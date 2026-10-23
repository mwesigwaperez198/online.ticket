<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Services\PaymentGateway\PaystackMomoGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Terminal checklist for a Novara production deployment.
 *
 * The command audits the environment, the payment wiring, the route
 * isolation, the database indexes and the deployment optimizers in one pass
 * so a release can be signed off without reading the deployment logs by hand.
 */
class DeployCheckCommand extends Command
{
    protected $signature = 'ticketing:deploy-check {--json : Print the checklist as JSON}';

    protected $description = 'Audit the production configuration, route isolation and database indexes';

    /**
     * Indexes the marketplace read paths depend on.
     *
     * @var array<string, array<int, string>>
     */
    private const EXPECTED_INDEXES = [
        'organizers' => ['organizers_user_id_index'],
        'listings' => ['listings_organizer_id_index', 'listings_organizer_id_status_index'],
        'orders' => ['orders_user_id_index', 'orders_listing_id_status_index', 'orders_gateway_reference_index'],
        'order_items' => ['order_items_order_id_index'],
        'tickets' => ['tickets_order_id_index', 'tickets_holder_email_index'],
        'payments' => ['payments_order_id_index'],
        'wallet_transactions' => ['wallet_transactions_order_id_index', 'wallet_transactions_wallet_id_index'],
        'refunds' => ['refunds_order_id_index'],
    ];

    /**
     * @var array<int, array{check:string, result:string, detail:string}>
     */
    private array $results = [];

    public function handle(): int
    {
        $this->checkEnvironment();
        $this->checkPayments();
        $this->checkPlatform();
        $this->checkAdministrator();
        $this->checkMail();
        $this->checkDatabase();
        $this->checkCaches();
        $this->checkRouteIsolation();
        $this->checkIndexes();
        $this->checkStorage();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'results' => $this->results,
                'summary' => $this->summary(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $summary = $this->summary();

        $this->newLine();

        if ($summary['failed'] > 0) {
            $this->components->error(sprintf(
                '%d check(s) failed, %d warning(s), %d passed.',
                $summary['failed'],
                $summary['warnings'],
                $summary['passed'],
            ));

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'All %d checks passed with %d warning(s).',
            $summary['passed'],
            $summary['warnings'],
        ));

        return self::SUCCESS;
    }

    private function checkEnvironment(): void
    {
        $this->section('Environment');

        $environment = (string) config('app.env');
        $this->record('Application environment', $environment === 'production' ? 'pass' : 'warn', $environment);

        $debug = (bool) config('app.debug');
        $this->record(
            'Debug mode disabled',
            $debug ? 'fail' : 'pass',
            $debug ? 'APP_DEBUG must be false in production' : 'APP_DEBUG=false',
        );

        $key = (string) config('app.key');
        $this->record('Application key', $key !== '' ? 'pass' : 'fail', $key !== '' ? 'configured' : 'APP_KEY is missing');

        $url = (string) config('app.url');
        $this->record('Application URL', $url !== '' ? 'pass' : 'fail', $url !== '' ? $url : 'APP_URL is missing');

        $timezone = (string) config('app.timezone');
        $this->record(
            'Timezone',
            $timezone === 'Africa/Kampala' ? 'pass' : 'warn',
            $timezone,
        );
    }

    private function checkPayments(): void
    {
        $this->section('Mobile Money');

        $secret = trim((string) config('ticketing.paystack.secret_key'));
        $public = trim((string) config('ticketing.paystack.public_key'));

        $this->record('Paystack secret key', $secret !== '' ? 'pass' : 'fail', $secret !== '' ? 'configured' : 'PAYSTACK_SECRET_KEY is missing');
        $this->record('Paystack public key', $public !== '' ? 'pass' : 'warn', $public !== '' ? 'configured' : 'PAYSTACK_PUBLIC_KEY is missing');

        $webhook = trim((string) config('ticketing.paystack.webhook_url'));
        $this->record('Webhook URL', $webhook !== '' ? 'pass' : 'warn', $webhook !== '' ? $webhook : 'PAYSTACK_WEBHOOK_URL is missing');

        $mode = strtolower(trim((string) config('ticketing.momo.mode', 'live')));
        $this->record(
            'Gateway mode',
            $mode === 'live' ? 'pass' : 'fail',
            $mode === 'live' ? 'live' : 'MOMO_MODE must be "live", payments are disabled otherwise',
        );

        $gateway = app(MomoGateway::class);
        $this->record(
            'Bound gateway',
            $gateway instanceof PaystackMomoGateway ? 'pass' : 'fail',
            $gateway::class,
        );
    }

    private function checkPlatform(): void
    {
        $this->section('Platform');

        $currency = (string) config('ticketing.ticketing.currency');
        $this->record('Currency', $currency === 'UGX' ? 'pass' : 'fail', $currency);

        $commission = (int) config('ticketing.ticketing.commission_bps');
        $this->record(
            'Default platform commission',
            $commission >= 0 && $commission <= 10_000 ? 'pass' : 'fail',
            $commission.' bps',
        );

        foreach (['brand.name' => 'BRAND_NAME', 'brand.email' => 'BRAND_EMAIL', 'brand.whatsapp' => 'BRAND_WHATSAPP'] as $key => $label) {
            $value = trim((string) config('ticketing.'.$key, ''));
            $this->record($label, $value !== '' ? 'pass' : 'warn', $value !== '' ? $value : $label.' is not configured');
        }
    }

    private function checkAdministrator(): void
    {
        $this->section('Administrator');

        $email = trim((string) config('ticketing.admin.email'));
        $password = (string) config('ticketing.admin.password');

        $this->record('Administrator email', $email !== '' ? 'pass' : 'fail', $email !== '' ? $email : 'ADMIN_EMAIL is missing');
        $this->record('Administrator password', $password !== '' ? 'pass' : 'warn', $password !== '' ? 'configured' : 'ADMIN_PASSWORD is missing');
    }

    private function checkMail(): void
    {
        $this->section('Mail');

        $from = trim((string) config('mail.from.address'));
        $this->record('Mail sender', $from !== '' ? 'pass' : 'warn', $from !== '' ? $from : 'MAIL_FROM_ADDRESS is missing');

        $mailer = (string) config('mail.default');
        $this->record('Mail transport', $mailer !== '' ? 'pass' : 'warn', $mailer);

        if ($mailer === 'smtp') {
            $host = trim((string) config('mail.mailers.smtp.host'));
            $this->record('SMTP host', $host !== '' ? 'pass' : 'fail', $host !== '' ? $host : 'MAIL_HOST is missing');
        }
    }

    private function checkDatabase(): void
    {
        $this->section('Database');

        $connection = (string) config('database.default');

        try {
            DB::connection($connection)->getPdo();
            $this->record('Connection', 'pass', $connection);
        } catch (\Throwable $e) {
            $this->record('Connection', 'fail', $e->getMessage());

            return;
        }

        $pending = $this->pendingMigrations();

        $this->record(
            'Migrations applied',
            $pending === [] ? 'pass' : 'fail',
            $pending === [] ? 'up to date' : count($pending).' pending: '.implode(', ', $pending),
        );
    }

    private function checkCaches(): void
    {
        $this->section('Deployment optimizers');

        $production = config('app.env') === 'production';

        $this->record(
            'Configuration cached',
            ! $production || $this->isCached('config') ? 'pass' : 'fail',
            $this->isCached('config') ? 'php artisan config:cache' : 'not cached',
        );

        $this->record(
            'Routes cached',
            ! $production || $this->isCached('routes') ? 'pass' : 'fail',
            $this->isCached('routes') ? 'php artisan route:cache' : 'not cached',
        );

        $this->record(
            'Views cached',
            ! $production || $this->isCached('views') ? 'pass' : 'fail',
            $this->isCached('views') ? 'php artisan view:cache' : 'not cached',
        );
    }

    private function checkRouteIsolation(): void
    {
        $this->section('Route isolation');

        $unprotected = [];
        $missingRole = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'admin') && ! str_starts_with($uri, 'organizer')) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            if (! in_array('auth', $middleware, true)) {
                $unprotected[] = $uri;
            }

            if (! collect($middleware)->contains(fn (string $item): bool => str_starts_with($item, 'role:'))) {
                $missingRole[] = $uri;
            }
        }

        $this->record(
            'Organizer and admin routes require authentication',
            $unprotected === [] ? 'pass' : 'fail',
            $unprotected === [] ? 'all guarded' : implode(', ', $unprotected),
        );

        $this->record(
            'Organizer and admin routes enforce a role',
            $missingRole === [] ? 'pass' : 'fail',
            $missingRole === [] ? 'all scoped' : implode(', ', $missingRole),
        );

        $webhook = Route::getRoutes()->getByName('api.v1.momo.webhook');
        $webhookMiddleware = $webhook === null ? [] : $webhook->gatherMiddleware();

        $this->record(
            'Webhook signature verification',
            in_array('webhook.signature', $webhookMiddleware, true) ? 'pass' : 'fail',
            in_array('webhook.signature', $webhookMiddleware, true) ? 'webhook.signature' : 'the webhook route is not protected',
        );

        $this->record(
            'Webhook throttled',
            collect($webhookMiddleware)->contains(fn (string $item): bool => str_starts_with($item, 'throttle:')) ? 'pass' : 'fail',
            implode(', ', $webhookMiddleware),
        );

        $checkout = Route::getRoutes()->getByName('checkout.store');
        $checkoutMiddleware = $checkout === null ? [] : $checkout->gatherMiddleware();

        $this->record(
            'Checkout throttled',
            collect($checkoutMiddleware)->contains(fn (string $item): bool => str_starts_with($item, 'throttle:')) ? 'pass' : 'fail',
            implode(', ', $checkoutMiddleware),
        );
    }

    private function checkIndexes(): void
    {
        $this->section('Database indexes');

        foreach (self::EXPECTED_INDEXES as $table => $indexes) {
            try {
                $present = array_column(Schema::getIndexes($table), 'name');
            } catch (\Throwable) {
                $this->record('Indexes on '.$table, 'warn', 'the table does not exist yet');

                continue;
            }

            $missing = array_values(array_diff($indexes, $present));

            $this->record(
                'Indexes on '.$table,
                $missing === [] ? 'pass' : 'fail',
                $missing === [] ? count($indexes).' present' : 'missing: '.implode(', ', $missing),
            );
        }
    }

    private function checkStorage(): void
    {
        $this->section('Storage');

        $writable = is_writable(storage_path());
        $this->record('Storage directory writable', $writable ? 'pass' : 'fail', storage_path());

        $linked = File::exists(public_path('storage'));
        $this->record('Public storage linked', $linked ? 'pass' : 'warn', $linked ? 'public/storage' : 'run php artisan storage:link');

        if (filled(env('RENDER_SERVICE_ID'))) {
            $databasePath = (string) config('database.connections.sqlite.database');
            $databaseOnDisk = str_starts_with($databasePath, '/var/data/');
            $this->record(
                'Persistent database',
                $databaseOnDisk ? 'pass' : 'fail',
                $databaseOnDisk ? $databasePath : 'SQLite database must be stored under /var/data',
            );

            $publicRoot = (string) config('filesystems.disks.public.root');
            $persistentRoot = realpath('/var/data');
            $resolvedRoot = realpath($publicRoot);
            $resolvedLink = realpath(public_path('storage'));
            $rootOnDisk = $persistentRoot !== false
                && $resolvedRoot !== false
                && str_starts_with($resolvedRoot, rtrim($persistentRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
            $linkCorrect = $resolvedRoot !== false && $resolvedLink === $resolvedRoot;
            $writable = is_dir($publicRoot) && is_writable($publicRoot);
            $this->record(
                'Persistent upload storage',
                $rootOnDisk && $linkCorrect && $writable ? 'pass' : 'fail',
                sprintf('root=%s; mounted=%s; link=%s; writable=%s', $publicRoot, $rootOnDisk ? 'yes' : 'no', $linkCorrect ? 'yes' : 'no', $writable ? 'yes' : 'no'),
            );

            return;
        }

        $this->record('Persistent database', 'warn', 'Render disk check skipped outside Render.');
        $this->record('Persistent upload storage', 'warn', 'Render disk check skipped outside Render.');
    }

    /**
     * @return array<int, string>
     */
    private function pendingMigrations(): array
    {
        $files = File::glob(database_path('migrations/*.php')) ?: [];
        $names = array_map(fn (string $file): string => Str::before(basename($file), '.php'), $files);

        try {
            $applied = DB::table('migrations')->pluck('migration')->all();
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_diff($names, $applied));
    }

    private function isCached(string $key): bool
    {
        return match ($key) {
            'config' => app()->configurationIsCached(),
            'routes' => app()->routesAreCached(),
            'views' => File::isDirectory(storage_path('framework/views'))
                && File::files(storage_path('framework/views')) !== [],
            default => false,
        };
    }

    private function record(string $check, string $result, string $detail): void
    {
        $this->results[] = ['check' => $check, 'result' => $result, 'detail' => $detail];

        if ($this->option('json')) {
            return;
        }

        $label = match ($result) {
            'pass' => '<fg=green>PASS</>',
            'warn' => '<fg=yellow>WARN</>',
            default => '<fg=red>FAIL</>',
        };

        $this->components->twoColumnDetail($label, $check.($detail !== '' ? ' — '.$detail : ''));
    }

    private function section(string $title): void
    {
        if ($this->option('json')) {
            return;
        }

        $this->line('<fg=cyan;options=bold>'.$title.'</>');
    }

    /**
     * @return array{passed:int, warnings:int, failed:int}
     */
    private function summary(): array
    {
        $counts = ['passed' => 0, 'warnings' => 0, 'failed' => 0];

        foreach ($this->results as $result) {
            if ($result['result'] === 'pass') {
                $counts['passed']++;
            } elseif ($result['result'] === 'warn') {
                $counts['warnings']++;
            } else {
                $counts['failed']++;
            }
        }

        return $counts;
    }
}
