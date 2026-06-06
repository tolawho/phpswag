<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwag\Core;

$core = new Core();
$core->setFilterUnusedSchemas(true);
$yaml = $core->generate([__DIR__ . '/App']);

echo $yaml;
