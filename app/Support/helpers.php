<?php

declare(strict_types=1);

use App\Models\Setting;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::value($key, $default);
    }
}
