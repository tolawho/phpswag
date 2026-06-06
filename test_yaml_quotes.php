<?php
require_once __DIR__ . '/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

$data = [
    'responses' => [
        '200' => ['description' => 'OK'],
    ]
];

echo "Default:\n";
echo Yaml::dump($data);

echo "\nWith DUMP_NUMERIC_KEY_AS_STRING (not a constant in 6.0, it was an option in some versions):\n";
// Actually it's not a bitmask in all versions.

echo "\nUsing a bitmask if supported:\n";
// In Symfony 6.x, Yaml::dump(data, inline, indent, flags)
// Flags: https://github.com/symfony/yaml/blob/6.4/Yaml.php
// There is no specific flag for "quote all numeric keys" easily found.

// Wait, I can try to use a different approach.
