<?php

namespace PhpSwag;

use Symfony\Component\Finder\Finder;

class Scanner
{
    private array $paths = [];
    private array $excludedPaths = ['vendor'];

    public function __construct(array $paths = [])
    {
        $this->paths = $paths;
    }

    public function setPaths(array $paths): void
    {
        $this->paths = $paths;
    }

    public function setExcludedPaths(array $excludedPaths): void
    {
        $this->excludedPaths = $excludedPaths;
    }

    public function scan(): array
    {
        if (empty($this->paths)) {
            return [];
        }

        $files = [];
        $dirs = [];

        foreach ($this->paths as $path) {
            if (is_file($path)) {
                $files[] = realpath($path);
            } elseif (is_dir($path)) {
                $dirs[] = $path;
            }
        }

        if (!empty($dirs)) {
            $finder = new Finder();
            $finder->files()
                ->in($dirs)
                ->name('*.php')
                ->exclude($this->excludedPaths);

            foreach ($finder as $file) {
                $files[] = $file->getRealPath();
            }
        }

        return array_unique($files);
    }
}
