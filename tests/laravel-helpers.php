<?php

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return $path;
    }
}
if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return $path;
    }
}
if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return $path;
    }
}
if (!function_exists('config')) {
    function config(string $key = null, mixed $default = null): mixed
    {
        global $laravelConfig;
        if ($key === null) {
            return $laravelConfig;
        }
        return $laravelConfig[$key] ?? $default;
    }
}
