<?php

namespace App\Models;

/**
 * @property string $title Post title
 * @property string $content Post body content
 */
class Post extends BaseModel
{
    use Timestampable;
}
