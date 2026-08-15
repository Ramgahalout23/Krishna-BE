<?php

namespace App\OpenApi\Sections;

class HealthSection
{
    public static function paths(): array
    {
        return [
            '/health' => [
                'get' => [
                    'summary' => 'Basic health check',
                    'tags' => ['Health'],
                    'responses' => [
                        '200' => [
                            'description' => 'Server is healthy',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'status' => ['type' => 'string', 'example' => 'healthy'],
                                            'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/health/status' => [
                'get' => [
                    'summary' => 'Detailed health check with version',
                    'tags' => ['Health'],
                    'responses' => [
                        '200' => [
                            'description' => 'Server status and version',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'status' => ['type' => 'string', 'example' => 'healthy'],
                                            'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                                            'version' => ['type' => 'string', 'example' => '1.0.0'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/email/health' => [
                'get' => [
                    'summary' => 'Email service health',
                    'tags' => ['Health'],
                    'responses' => [
                        '200' => [
                            'description' => 'Email service status',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'status' => ['type' => 'string', 'example' => 'healthy'],
                                            'service' => ['type' => 'string', 'example' => 'smtp'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/broadcasting/config' => [
                'get' => [
                    'summary' => 'Get broadcasting config',
                    'tags' => ['Broadcasting'],
                    'responses' => [
                        '200' => [
                            'description' => 'Broadcasting configuration',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'driver' => ['type' => 'string', 'example' => 'pusher'],
                                            'key' => ['type' => 'string', 'nullable' => true],
                                            'cluster' => ['type' => 'string', 'nullable' => true],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/docs/json' => [
                'get' => [
                    'summary' => 'Get OpenAPI JSON spec',
                    'tags' => ['Documentation'],
                    'responses' => [
                        '200' => [
                            'description' => 'OpenAPI specification',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'description' => 'Full OpenAPI 3.0.3 specification',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/docs' => [
                'get' => [
                    'summary' => 'Swagger UI documentation page',
                    'tags' => ['Documentation'],
                    'responses' => [
                        '200' => [
                            'description' => 'Swagger UI HTML page',
                            'content' => ['text/html' => ['schema' => ['type' => 'string']]],
                        ],
                    ],
                ],
            ],
        ];
    }
}
