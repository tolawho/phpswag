<?php

namespace App\Models;

/**
 * @property int $id format(int64)
 * @property \App\Models\Category $category
 * @property string $name example(doggie)
 * @property string[] $photoUrls
 * @property \App\Models\Tag[] $tags
 * @property string $status enum(available,pending,sold) pet status in the store
 */
class Pet
{
}
