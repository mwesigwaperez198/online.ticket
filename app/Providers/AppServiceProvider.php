<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\HoldExpiryService;
use App\Services\PaymentGateway\Contracts\MomoGateway;
use App\Services\PaymentGateway\DemoMomoGateway;
use App\Services\PaymentGateway\PaystackMomoGateway;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MomoGateway::class, function (): MomoGateway {
            $secret = (string) config('ticketing.paystack.secret_key');

            if ($secret === '') {
                return new DemoMomoGateway;
            }

            return new PaystackMomoGateway($secret, (string) config('ticketing.paystack.base_url'));
        });

        $this->app->singleton(HoldExpiryService::class);
    }

    public function boot(): void
    {
        require_once base_path('app/Support/helpers.php');

        Blade::directive('money', static fn (string $expression): string => "<?php echo \\App\\Support\\Money::format({$expression}); ?>");
        Blade::directive('moneyRaw', static fn (string $expression): string => "<?php echo number_format({$expression} / 100, 2); ?>");
    }
}
