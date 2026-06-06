<?php
require_once __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$data = [
    'responses' => [
        '200' => ['description' => 'OK'],
    ]
];

$yaml = Yaml::dump($data, 10, 2);
$yaml = preg_replace('/^(\s*)(\d+):/m', '$1\'$2\':', $yaml);
echo $yaml;
