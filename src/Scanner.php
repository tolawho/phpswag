<?php

namespace PhpSwag;

use Symfony\Component\Finder\Finder;

class Scanner
{
    /** @var array<int, string> */
    private array $paths = [];
    /** @var array<int, string> */
    private array $excludedPaths = ['vendor'];

    /**
     * @param array<int, string> $paths
     */
    public function __construct(array $paths = [])
    {
        $this->paths = $paths;
    }

    /**
     * @param array<int, string> $paths
     */
    public function setPaths(array $paths): void
    {
        $this->paths = $paths;
    }

    /**
     * @param array<int, string> $excludedPaths
     */
    public function setExcludedPaths(array $excludedPaths): void
    {
        $this->excludedPaths = $excludedPaths;
    }

    /**
     * @return array<int, string>
     */
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
