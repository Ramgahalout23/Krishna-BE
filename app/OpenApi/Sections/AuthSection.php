<?php

namespace App\OpenApi\Sections;

class AuthSection
{
    public static function paths(): array
    {
        return [
            '/auth/register' => [
                'post' => [
                    'summary' => 'Register a new user',
                    'tags' => ['Auth'],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/RegisterRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'User registered successfully',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/RegisterResponse']]],
                        ],
                        '422' => ['description' => 'Validation error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationError']]]],
                    ],
                ],
            ],
            '/auth/login' => [
                'post' => [
                    'summary' => 'Login with email/password',
                    'tags' => ['Auth'],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/LoginRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Login successful with token',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/LoginResponse']]],
                        ],
                        '401' => ['description' => 'Invalid credentials', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorResponse']]]],
                        '422' => ['description' => 'Validation error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationError']]]],
                    ],
                ],
            ],
            '/auth/logout' => [
                'post' => [
                    'summary' => 'Logout (invalidate token)',
                    'tags' => ['Auth'],
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => []]]]],
                    'responses' => [
                        '200' => ['description' => 'Logged out successfully', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/MessageResponse']]]],
                        '401' => ['description' => 'Unauthenticated'],
                    ],
                ],
            ],
            '/auth/me' => [
                'get' => [
                    'summary' => 'Get current authenticated user',
                    'tags' => ['Auth'],
                    'security' => [['bearerAuth' => []]],
                    'responses' => [
                        '200' => [
                            'description' => 'Current user profile',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/UserResponse']]],
                        ],
                        '401' => ['description' => 'Unauthenticated'],
                    ],
                ],
            ],
            '/auth/profile' => [
                'put' => [
                    'summary' => 'Update profile (name, email, avatar)',
                    'tags' => ['Auth'],
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/UpdateProfileRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Profile updated',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ProfileUpdatedResponse']]],
                        ],
                        '401' => ['description' => 'Unauthenticated'],
                        '422' => ['description' => 'Validation error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationError']]]],
                    ],
                ],
            ],
            '/auth/change-password' => [
                'post' => [
                    'summary' => 'Change password',
                    'tags' => ['Auth'],
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ChangePasswordRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Password changed', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/MessageResponse']]]],
                        '401' => ['description' => 'Unauthenticated'],
                        '422' => ['description' => 'Validation error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationError']]]],
                    ],
                ],
            ],
            '/auth/forgot-password' => [
                'post' => [
                    'summary' => 'Send password reset email',
                    'tags' => ['Auth'],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ForgotPasswordRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Reset link sent', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/MessageResponse']]]],
                        '422' => ['description' => 'Validation error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationError']]]],
                    ],
                ],
            ],
            '/auth/reset-password' => [
                'post' => [
                    'summary' => 'Reset password with token',
                    'tags' => ['Auth'],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ResetPasswordRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Password reset', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/MessageResponse']]]],
                        '422' => ['description' => 'Validation error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationError']]]],
                    ],
                ],
            ],
            '/auth/send-otp' => [
                'post' => [
                    'summary' => 'Send OTP to phone',
                    'tags' => ['Auth'],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/SendOtpRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'OTP sent', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/MessageResponse']]]],
                        '422' => ['description' => 'Validation error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationError']]]],
                    ],
                ],
            ],
            '/auth/verify-otp' => [
                'post' => [
                    'summary' => 'Verify OTP code',
                    'tags' => ['Auth'],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/VerifyOtpRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'OTP verified',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/LoginResponse']]],
                        ],
                        '422' => ['description' => 'Invalid OTP or validation error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationError']]]],
                    ],
                ],
            ],
            '/auth/send-verification' => [
                'post' => [
                    'summary' => 'Send email verification link',
                    'tags' => ['Auth'],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ForgotPasswordRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Verification sent', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/MessageResponse']]]],
                        '422' => ['description' => 'Validation error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationError']]]],
                    ],
                ],
            ],
            '/auth/verify-email' => [
                'post' => [
                    'summary' => 'Verify email address',
                    'tags' => ['Auth'],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/VerifyEmailRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Email verified', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/MessageResponse']]]],
                        '422' => ['description' => 'Validation error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationError']]]],
                    ],
                ],
            ],
            '/auth/oauth/status' => [
                'get' => [
                    'summary' => 'Check OAuth provider status',
                    'tags' => ['Auth'],
                    'responses' => [
                        '200' => [
                            'description' => 'OAuth provider status',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'allOf' => [
                                            ['$ref' => '#/components/schemas/SuccessResponse'],
                                            [
                                                'properties' => [
                                                    'data' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'providers' => [
                                                                'type' => 'object',
                                                                'properties' => [
                                                                    'google' => [
                                                                        'type' => 'object',
                                                                        'properties' => [
                                                                            'enabled' => ['type' => 'boolean'],
                                                                            'client_id' => ['type' => 'string', 'nullable' => true],
                                                                        ],
                                                                    ],
                                                                    'facebook' => [
                                                                        'type' => 'object',
                                                                        'properties' => [
                                                                            'enabled' => ['type' => 'boolean'],
                                                                            'client_id' => ['type' => 'string', 'nullable' => true],
                                                                        ],
                                                                    ],
                                                                ],
                                                            ],
                                                            'strategies' => ['type' => 'array', 'items' => ['type' => 'string']],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            '/auth/refresh-token' => [
                'post' => [
                    'summary' => 'Refresh auth token (revokes current, issues new)',
                    'tags' => ['Auth'],
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => []]]]],
                    'responses' => [
                        '200' => [
                            'description' => 'Token refreshed with new token',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessWithMessage']]],
                        ],
                        '401' => ['description' => 'Unauthenticated'],
                    ],
                ],
            ],
            '/auth/refresh-oauth' => [
                'post' => [
                    'summary' => 'Admin: refresh OAuth strategies from settings',
                    'tags' => ['Auth'],
                    'security' => [['bearerAuth' => []]],
                    'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => []]]]],
                    'responses' => [
                        '200' => ['description' => 'OAuth strategies refreshed', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessWithMessage']]]],
                    ],
                ],
            ],
            '/auth/{provider}' => [
                'get' => [
                    'summary' => 'Redirect to OAuth provider (google|facebook)',
                    'tags' => ['Auth'],
                    'parameters' => [
                        ['name' => 'provider', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['google', 'facebook']]],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Redirect URL',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'allOf' => [
                                            ['$ref' => '#/components/schemas/SuccessResponse'],
                                            [
                                                'properties' => [
                                                    'data' => [
                                                        'type' => 'object',
                                                        'properties' => [
                                                            'redirect_url' => ['type' => 'string'],
                                                            'provider' => ['type' => 'string'],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '400' => ['description' => 'Unsupported provider or not configured', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorResponse']]]],
                    ],
                ],
            ],
            '/auth/{provider}/callback' => [
                'get' => [
                    'summary' => 'Handle OAuth provider callback',
                    'tags' => ['Auth'],
                    'parameters' => [
                        ['name' => 'provider', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['google', 'facebook']]],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'OAuth login successful',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/LoginResponse']]],
                        ],
                        '401' => ['description' => 'Authentication failed', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ErrorResponse']]]],
                    ],
                ],
            ],
        ];
    }
}
