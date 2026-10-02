<?php

use App\Support\Settings;
use App\Support\Thai;

if (! function_exists('thai_date')) {
    function thai_date($date, bool $long = false): string
    {
        return Thai::date($date, $long);
    }
}

if (! function_exists('thai_datetime')) {
    function thai_datetime($date): string
    {
        return Thai::dateTime($date);
    }
}

if (! function_exists('baht')) {
    function baht($amount, int $decimals = 2): string
    {
        return Thai::money($amount, $decimals);
    }
}

if (! function_exists('school')) {
    function school(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}
