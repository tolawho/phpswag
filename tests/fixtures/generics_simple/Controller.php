<?php namespace App;
        use App\ApiResponse;
        use App\User;
        class Controller {
            /**
             * @route GET /user
             * @response 200 ApiResponse<User>
             */
            public function getUser() {}
        }