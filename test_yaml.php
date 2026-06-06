<?php
require_once __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$data = [
    'responses' => [
        '200' => ['description' => 'OK'],
        'default' => ['description' => 'Error']
    ]
];

echo Yaml::dump($data);
