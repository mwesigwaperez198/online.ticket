<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\HoldExpiryService;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Services\PaymentGateway\DemoMomoGateway;
use App\Services\PaymentGateway\MtnMomoGateway;
use App\Services\PaymentGateway\PaystackMomoGateway;
use App\Services\PaymentGateway\PesapalMomoGateway;
use App\Services\PaymentGateway\UnavailableMomoGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MomoGateway::class, function (): MomoGateway {
            $mode = strtolower(trim((string) config('ticketing.momo.mode', 'live')));

            if ($this->app->environment('production') && $mode !== 'live') {
                Log::critical(sprintf(
                    'MOMO_MODE is "%s" but a production deployment must run in "live" mode — Mobile Money payments are disabled.',
                    $mode,
                ));

                return new UnavailableMomoGateway;
            }

            $gateway = strtolower(trim((string) config('ticketing.momo.gateway', 'pesapal')));

            if ($gateway === 'mtn') {
                return $this->mtnGateway();
            }

            if ($gateway === 'pesapal') {
                return $this->pesapalGateway();
            }

            $secret = trim((string) config('ticketing.paystack.secret_key'));

            if ($secret === '') {
                if ($this->app->environment('production')) {
                    Log::critical('PAYSTACK_SECRET_KEY is not set — Mobile Money payments are disabled on this deployment.');
                    return new UnavailableMomoGateway;
                }

                return new DemoMomoGateway;
            }

            return new PaystackMomoGateway(
                $secret,
                (string) config('ticketing.paystack.base_url'),
                (string) config('ticketing.paystack.currency', 'UGX'),
            );
        });

        $this->app->singleton(HoldExpiryService::class);
    }

    private function mtnGateway(): MomoGateway
    {
        $primary = trim((string) config('ticketing.momo.mtn.primary_key'));
        $secondary = trim((string) config('ticketing.momo.mtn.secondary_key'));

        if ($primary === '' && $secondary === '') {
            Log::critical('No MTN subscription key is configured — Mobile Money payments are disabled on this deployment.');

            return new UnavailableMomoGateway;
        }

        return new MtnMomoGateway(
            $primary,
            $secondary,
            (string) config('ticketing.momo.mtn.base_url'),
            (string) config('ticketing.momo.mtn.target_environment'),
            (string) config('ticketing.momo.mtn.currency', 'UGX'),
            (string) config('ticketing.momo.mtn.payee', 'Novara'),
            (string) config('ticketing.momo.mtn.callback_url', ''),
        );
    }

    private function pesapalGateway(): MomoGateway
    {
        $consumerKey = trim((string) config('ticketing.momo.pesapal.consumer_key'));
        $consumerSecret = trim((string) config('ticketing.momo.pesapal.consumer_secret'));

        if ($consumerKey === '' || $consumerSecret === '') {
            Log::critical('Pesapal consumer key or secret is not configured — Mobile Money payments are disabled on this deployment.');

            return new UnavailableMomoGateway;
        }

        return new PesapalMomoGateway(
            $consumerKey,
            $consumerSecret,
            (string) config('ticketing.momo.pesapal.base_url', 'https://sandbox.pesapal.com'),
            (string) config('ticketing.momo.pesapal.currency', 'UGX'),
            (string) config('ticketing.momo.pesapal.callback_url', ''),
        );
    }

    public function boot(): void
    {
        require_once base_path('app/Support/helpers.php');

        $this->registerRateLimiters();

        Blade::directive('money', static fn (string $expression): string => "<?php echo \\App\\Support\\Money::format({$expression}); ?>");
        Blade::directive('moneyRaw', static fn (string $expression): string => "<?php echo number_format({$expression} / 100, 2); ?>");
    }

    // rest of your file continues...
}
