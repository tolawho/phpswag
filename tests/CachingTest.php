<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Core;

class CachingTest extends TestCase
{
    public function testCachingFlow()
    {
        $code = <<<'PHP'
<?php
namespace App\Controllers;

class UserController {
    /**
     * @route GET /users
     * @response 200 string
     */
    public function list() {}
}
PHP;

        $tempFile = tempnam(sys_get_temp_dir(), 'phpswag-cache-test');
        file_put_contents($tempFile, $code);

        $cacheFile = sys_get_temp_dir() . '/phpswag-cache-test-store.dat';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }

        // Run 1: Cold start (caching enabled, file is parsed and cache is written)
        $core1 = new Core();
        $core1->enableCache($cacheFile);
        $yaml1 = $core1->generateYaml([$tempFile]);

        $this->assertFileExists($cacheFile);

        // Run 2: Hot start (file is loaded from cache)
        $core2 = new Core();
        $core2->enableCache($cacheFile);
        $yaml2 = $core2->generateYaml([$tempFile]);

        $this->assertEquals($yaml1, $yaml2);

        // Clean up
        unlink($tempFile);
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }
}
