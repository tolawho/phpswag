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

        $finder = new Finder();
        $finder->files()
            ->in($this->paths)
            ->name('*.php')
            ->exclude($this->excludedPaths);

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }

        return $files;
    }
}
