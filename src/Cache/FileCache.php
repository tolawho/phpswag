<?php

namespace PhpSwag\Cache;

class FileCache implements CacheInterface
{
    private string $filePath;
    /** @var array<string, mixed> */
    private array $data = [];
    private bool $loaded = false;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        if (file_exists($this->filePath)) {
            $content = file_get_contents($this->filePath);
            if ($content !== false) {
                try {
                    $decoded = unserialize($content);
                    if (is_array($decoded)) {
                        $this->data = $decoded;
                    }
                } catch (\Throwable $e) {
                    $this->data = [];
                }
            }
        }

        $this->loaded = true;
    }

    private function save(): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($this->filePath, serialize($this->data), LOCK_EX);
    }

    public function get(string $key): mixed
    {
        $this->load();
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->load();
        $this->data[$key] = $value;
        $this->save();
    }

    public function clear(): void
    {
        $this->data = [];
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
        $this->loaded = true;
    }
}
