<?php
require_once __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$data = [
    'responses' => [
        '200' => ['description' => 'OK'],
    ]
];

echo Yaml::dump($data, 10, 2, Yaml::DUMP_NUMERIC_KEY_AS_STRING);
