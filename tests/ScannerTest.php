<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Scanner;

class ScannerTest extends TestCase
{
    public function testScanFindsPhpFiles()
    {
        // Create a temporary directory for testing
        $testDir = __DIR__ . '/_temp_scanner_test';
        if (!is_dir($testDir)) {
            mkdir($testDir);
        }
        file_put_contents($testDir . '/file1.php', '<?php');
        file_put_contents($testDir . '/file2.php', '<?php');
        file_put_contents($testDir . '/not_php.txt', 'test');

        mkdir($testDir . '/sub');
        file_put_contents($testDir . '/sub/file3.php', '<?php');

        $scanner = new Scanner([$testDir]);
        $files = $scanner->scan();

        $this->assertCount(3, $files);
        $this->assertContains(realpath($testDir . '/file1.php'), $files);
        $this->assertContains(realpath($testDir . '/file2.php'), $files);
        $this->assertContains(realpath($testDir . '/sub/file3.php'), $files);

        // Cleanup
        $this->removeDir($testDir);
    }

    private function removeDir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object)) {
                        $this->removeDir($dir . DIRECTORY_SEPARATOR . $object);
                    } else {
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
