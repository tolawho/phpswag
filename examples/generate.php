<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpSwag\Core;

$core = new Core();
// Assuming you have App/Controllers and App/Models in examples/
$yaml = $core->generate([__DIR__ . '/App']);

echo $yaml;
