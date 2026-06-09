<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Core;

class GenericsTest extends TestCase
{
    public function testSimpleGenerics()
    {
        $dir = __DIR__ . '/fixtures/generics_simple';
        @mkdir($dir, 0777, true);

        file_put_contents($dir . '/ApiResponse.php', '<?php namespace App; /** @template T */ class ApiResponse { /** @var T */ public $data; }');
        file_put_contents($dir . '/User.php', '<?php namespace App; class User { /** @var string */ public $name; }');
        file_put_contents($dir . '/Controller.php', '<?php namespace App;
        use App\ApiResponse;
        use App\User;
        class Controller {
            /**
             * @route GET /user
             * @response 200 ApiResponse<User>
             */
            public function getUser() {}
        }');

        $core = new Core();
        $yaml = $core->generate([$dir]);

        $this->assertStringContainsString("App_ApiResponse.App_User:", $yaml);
        $this->assertStringContainsString("\$ref: '#/components/schemas/App_User'", $yaml);
    }

    public function testNestedGenerics()
    {
        $dir = __DIR__ . '/fixtures/generics_nested';
        @mkdir($dir, 0777, true);

        file_put_contents($dir . '/ApiResponse.php', '<?php namespace App; /** @template T */ class ApiResponse { /** @var T */ public $data; }');
        file_put_contents($dir . '/Collection.php', '<?php namespace App; /** @template T */ class Collection { /** @var T[] */ public $items; }');
        file_put_contents($dir . '/User.php', '<?php namespace App; class User { /** @var string */ public $name; }');
        file_put_contents($dir . '/Controller.php', '<?php namespace App;
        use App\ApiResponse;
        use App\Collection;
        use App\User;
        class Controller {
            /**
             * @route GET /users
             * @response 200 ApiResponse<Collection<User>>
             */
            public function getUsers() {}
        }');

        $core = new Core();
        $yaml = $core->generate([$dir]);

        $this->assertStringContainsString("App_ApiResponse.App_Collection.App_User:", $yaml);
        $this->assertStringContainsString("App_Collection.App_User:", $yaml);
    }

    public function testGenericInheritance()
    {
        $dir = __DIR__ . '/fixtures/generics_inheritance';
        @mkdir($dir, 0777, true);

        file_put_contents($dir . '/BaseResponse.php', '<?php namespace App; /** @template T */ class BaseResponse { /** @var T */ public $data; }');
        file_put_contents($dir . '/UserResponse.php', '<?php namespace App; use App\BaseResponse; /** @extends BaseResponse<User> */ class UserResponse extends BaseResponse { /** @var string */ public $message; }');
        file_put_contents($dir . '/User.php', '<?php namespace App; class User { /** @var string */ public $name; }');

        $core = new Core();
        $yaml = $core->generate([$dir]);

        $this->assertStringContainsString("App_UserResponse:", $yaml);
        $this->assertStringContainsString("data:", $yaml);
        $this->assertStringContainsString("message:", $yaml);
        $this->assertStringContainsString("App_User", $yaml);
    }

    public function testGenericsWithConstraints()
    {
        $dir = __DIR__ . '/fixtures/generics_constraints';
        @mkdir($dir, 0777, true);

        file_put_contents($dir . '/BaseModel.php', '<?php namespace App; class BaseModel { public string $id; }');
        file_put_contents($dir . '/QueryFilter.php', '<?php namespace App; /** @template TModel of BaseModel */ class QueryFilter { }');
        file_put_contents($dir . '/SortFilter.php', '<?php namespace App; use App\QueryFilter; use App\BaseModel;
        /**
         * @template TModel of BaseModel
         *
         * @extends QueryFilter<TModel>
         */
        class SortFilter extends QueryFilter { }');
        file_put_contents($dir . '/Controller.php', '<?php namespace App;
        use App\SortFilter;
        use App\BaseModel;
        class Controller {
            /**
             * @route GET /sort
             * @response 200 SortFilter<BaseModel>
             */
            public function getSort() {}
        }');

        $core = new Core();
        $yaml = $core->generate([$dir]);

        $this->assertStringContainsString("App_SortFilter:", $yaml);
    }
}
