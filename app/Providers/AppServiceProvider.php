<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\HoldExpiryService;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Services\PaymentGateway\DemoMomoGateway;
use App\Services\PaymentGateway\MtnMomoGateway;
use App\Services\PaymentGateway\PaystackMomoGateway;
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

            $gateway = strtolower(trim((string) config('ticketing.momo.gateway', 'paystack')));

            if ($gateway === 'mtn') {
                return $this->mtnGateway();
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

    /**
     * The MTN MoMo Collections gateway.
     *
     * A deployment without an MTN subscription key must never settle a
     * payment, so the unavailable gateway is bound instead.
     */
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

    public function boot(): void
    {
        require_once base_path('app/Support/helpers.php');

        $this->registerRateLimiters();

        Blade::directive('money', static fn (string $expression): string => "<?php echo \\App\\Support\\Money::format({$expression}); ?>");
        Blade::directive('moneyRaw', static fn (string $expression): string => "<?php echo number_format({$expression} / 100, 2); ?>");
    }

    /**
     * Named rate limiters used by the route definitions.
     */
    private function registerRateLimiters(): void
    {
        /*
         * Checkout is the money path: five attempts a minute per address and
         * ten a minute per account keep a bot from burning ticket holds while
         * still letting a buyer retry a failed Mobile Money prompt.
         */
        RateLimiter::for('checkout', function (Request $request): array {
            return [
                Limit::perMinute(5)->by('checkout:ip:'.$request->ip()),
                Limit::perMinute(10)->by('checkout:user:'.($request->user()?->getAuthIdentifier() ?? 'guest')),
            ];
        });

        /*
         * The "check your phone" screen polls the order status while the
         * buyer authorises the charge, so it gets its own window instead of
         * the tight checkout limit.
         */
        RateLimiter::for('checkout-status', function (Request $request): Limit {
            return Limit::perMinute(60)->by('checkout-status:'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        // Brute force protection for the sign in and registration forms.
        RateLimiter::for('login', function (Request $request): Limit {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by('login:'.sha1($email.'|'.$request->ip()));
        });

        /*
         * Providers retry aggressively and in bursts, so the webhook gets a
         * wide window. The signature check is what actually protects the
         * endpoint; this only absorbs a retry storm.
         */
        RateLimiter::for('webhook', function (Request $request): Limit {
            return Limit::perMinute(120)->by('webhook:'.$request->ip());
        });

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by('api:'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });
    }
}
