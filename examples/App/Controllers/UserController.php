<?php

namespace App\Controllers;

use App\Models\User;

class UserController
{
    /**
     * @route GET /users
     * @summary List all users
     * @response 200 User
     */
    public function index()
    {
    }

    /**
     * @route GET /users/{id}
     * @summary Get user details
     * @response 200 User
     */
    public function show(int $id)
    {
    }
}
