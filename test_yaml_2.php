<?php
require_once __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$data = [
    '200' => ['description' => 'OK'],
];

echo "Inline 1:\n";
echo Yaml::dump($data, 1);
echo "Inline 2:\n";
echo Yaml::dump($data, 2);
echo "Inline 10:\n";
echo Yaml::dump($data, 10);
