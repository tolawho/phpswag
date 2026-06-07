<?php

namespace PhpSwag\Cache;

interface CacheInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function clear(): void;
}
