<?php

namespace App\Controllers;

use App\Models\Post;
use App\Models\ApiResponse;
use App\Models\Collection;

class PostController
{
    /**
     * @route GET /posts
     * @summary List all posts with pagination
     * @tag Posts
     * @response 200 ApiResponse<Collection<Post>>
     */
    public function index()
    {
    }

    /**
     * @route GET /posts/{id}
     * @summary Get a single post
     * @tag Posts
     * @response 200 ApiResponse<Post>
     */
    public function show(int $id)
    {
    }
}
