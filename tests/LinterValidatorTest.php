<?php

namespace PhpSwag\Tests;

use PHPUnit\Framework\TestCase;
use PhpSwag\Validation\Validator;

class LinterValidatorTest extends TestCase
{
    public function testValidSpecPasses()
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/users' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/User'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer']
                        ]
                    ]
                ]
            ]
        ];

        $validator = new Validator();
        $errors = $validator->validate($spec);

        $this->assertEmpty($errors);
    }

    public function testMissingInfoAndOpenApiVersion()
    {
        $spec = [
            'paths' => []
        ];

        $validator = new Validator();
        $errors = $validator->validate($spec);

        $this->assertContains("Missing 'openapi' version field.", $errors);
        $this->assertContains("Missing 'info' object.", $errors);
    }

    public function testMissingTitleAndVersionInInfo()
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [],
            'paths' => []
        ];

        $validator = new Validator();
        $errors = $validator->validate($spec);

        $this->assertContains("Missing 'info.title' field.", $errors);
        $this->assertContains("Missing 'info.version' field.", $errors);
    }

    public function testUnresolvedSchemaReference()
    {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Test API',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/users' => [
                    'get' => [
                        'responses' => [
                            '200' => [
                                'description' => 'Success',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            '$ref' => '#/components/schemas/NonExistentModel'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'components' => [
                'schemas' => [
                    'User' => [
                        'type' => 'object'
                    ]
                ]
            ]
        ];

        $validator = new Validator();
        $errors = $validator->validate($spec);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString("Unresolved schema reference: '#/components/schemas/NonExistentModel'", $errors[0]);
    }
}
