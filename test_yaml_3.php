<?php
require_once __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$data = [
    'responses' => [
        '200' => ['description' => 'OK'],
    ]
];

// Symfony Yaml 6.0 flags
// DUMP_OBJECT = 1
// DUMP_EXCEPTION_ON_INVALID_TYPE = 2
// DUMP_OBJECT_AS_MAP = 4
// DUMP_MULTI_LINE_LITERAL_BLOCK = 8
// DUMP_EMPTY_ARRAY_AS_SEQUENCE = 16
// DUMP_NULL_AS_TILDE = 32

echo Yaml::dump($data, 10, 2);
