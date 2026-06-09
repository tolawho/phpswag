<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Core;

class InheritanceTest extends TestCase
{
    public function testInheritanceMerging()
    {
        $dir = __DIR__ . '/fixtures/inheritance';
        @mkdir($dir, 0777, true);

        file_put_contents($dir . '/Base.php', '<?php namespace App; class Base { /** @var string */ public $id; }');
        file_put_contents($dir . '/User.php', '<?php namespace App; class User extends Base { /** @var string */ public $name; }');

        $core = new Core();
        $yaml = $core->generate([$dir]);

        $this->assertStringContainsString("App_User:", $yaml);
        $this->assertStringContainsString("id:", $yaml);
        $this->assertStringContainsString("name:", $yaml);
    }

    public function testTraitMerging()
    {
        $dir = __DIR__ . '/fixtures/traits';
        @mkdir($dir, 0777, true);

        file_put_contents($dir . '/Timestampable.php', '<?php namespace App; trait Timestampable { /** @var string */ public $createdAt; }');
        file_put_contents($dir . '/Post.php', '<?php namespace App; class Post { use Timestampable; /** @var string */ public $title; }');

        $core = new Core();
        $yaml = $core->generate([$dir]);

        $this->assertStringContainsString("App_Post:", $yaml);
        $this->assertStringContainsString("createdAt:", $yaml);
        $this->assertStringContainsString("title:", $yaml);
    }

    public function testOverride()
    {
        $dir = __DIR__ . '/fixtures/override';
        @mkdir($dir, 0777, true);

        file_put_contents($dir . '/Base.php', '<?php namespace App; class Base { /** @var string */ public $type = "base"; }');
        file_put_contents($dir . '/Child.php', '<?php namespace App; class Child extends Base { /** @var int */ public $type = 1; }');

        $core = new Core();
        $yaml = $core->generate([$dir]);

        $this->assertStringContainsString("App_Child:", $yaml);
        $this->assertStringContainsString("type:\n          type: integer", $yaml);
    }
}
