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
if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return $path;
    }
}
if (!function_exists('config')) {
    /**
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    function config(mixed $key = null, mixed $default = null): mixed
    {
        return $default;
    }
}
if (!function_exists('response')) {
    /**
     * @param string $content
     * @param int $status
     * @param array<string, string> $headers
     * @return mixed
     */
    function response(string $content = '', int $status = 200, array $headers = []): mixed
    {
        return null;
    }
}
