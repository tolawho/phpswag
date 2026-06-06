<?php

namespace App\Controllers;

use App\Models\User;

class UserController
{
    /**
     * @route GET /users
     * @summary List all users
     * @tag User Management
     * @response 200 User[]
     */
    public function index()
    {
    }

    /**
     * @route GET /users/{id}
     * @summary Get user details
     * @description This endpoint returns a single user by their ID.
     * @tag User Management
     * @response 200 User
     * @response 404 string
     */
    public function show(int $id)
    {
    }
}
