<?php namespace App;
        use App\ApiResponse;
        use App\Collection;
        use App\User;
        class Controller {
            /**
             * @route GET /users
             * @response 200 ApiResponse<Collection<User>>
             */
            public function getUsers() {}
        }