<?php

namespace App\Controllers;

class UserController
{
    /**
     * @route POST /user
     * @summary Create user.
     * @description This can only be done by the logged in user.
     * @operationId createUser
     * @tag user
     * @accept json, xml, x-www-form-urlencoded
     * @produce json, xml
     * @body \App\Models\User Created user object
     * @response 200 \App\Models\User successful operation
     * @response default void Unexpected error
     */
    public function createUser()
    {
    }

    /**
     * @route POST /user/createWithList
     * @summary Creates list of users with given input array.
     * @description Creates list of users with given input array.
     * @operationId createUsersWithListInput
     * @tag user
     * @accept json
     * @produce json, xml
     * @body \App\Models\User[] List of user object
     * @response 200 \App\Models\User Successful operation
     * @response default void Unexpected error
     */
    public function createUsersWithListInput()
    {
    }

    /**
     * @route GET /user/login
     * @summary Logs user into the system.
     * @description Log into the system.
     * @operationId loginUser
     * @tag user
     * @produce json, xml
     * @query string $username The user name for login
     * @query string $password The password for login in clear text
     * @response 200 string successful operation
     * @response 400 void Invalid username/password supplied
     * @response default void Unexpected error
     */
    public function loginUser()
    {
    }

    /**
     * @route GET /user/logout
     * @summary Logs out current logged in user session.
     * @description Log user out of the system.
     * @operationId logoutUser
     * @tag user
     * @produce json, xml
     * @response 200 void successful operation
     * @response default void Unexpected error
     */
    public function logoutUser()
    {
    }

    /**
     * @route GET /user/{username}
     * @summary Get user by user name.
     * @description Get user detail based on username.
     * @operationId getUserByName
     * @tag user
     * @produce json, xml
     * @path string $username The name that needs to be fetched. Use user1 for testing
     * @response 200 \App\Models\User successful operation
     * @response 400 void Invalid username supplied
     * @response 404 void User not found
     * @response default void Unexpected error
     */
    public function getUserByName(string $username)
    {
    }

    /**
     * @route PUT /user/{username}
     * @summary Update user resource.
     * @description This can only be done by the logged in user.
     * @operationId updateUser
     * @tag user
     * @accept json, xml, x-www-form-urlencoded
     * @produce json, xml
     * @path string $username name that need to be deleted
     * @body \App\Models\User Update an existent user in the store
     * @response 200 void successful operation
     * @response 400 void bad request
     * @response 404 void user not found
     * @response default void Unexpected error
     */
    public function updateUser(string $username)
    {
    }

    /**
     * @route DELETE /user/{username}
     * @summary Delete user resource.
     * @description This can only be done by the logged in user.
     * @operationId deleteUser
     * @tag user
     * @produce json, xml
     * @path string $username The name that needs to be deleted
     * @response 200 void User deleted
     * @response 400 void Invalid username supplied
     * @response 404 void User not found
     * @response default void Unexpected error
     */
    public function deleteUser(string $username)
    {
    }
}
