<?php

/**
 * JWT Configuration
 * 
 * Shared secret used to validate JWT tokens issued by the Node.js auth system.
 * This must match the 'config.jwt.secret' value in the Node.js backend.
 * 
 * Usage:
 *   config('jwt.secret')
 */
return [
    /*
    |--------------------------------------------------------------------------
    | JWT Secret
    |--------------------------------------------------------------------------
    |
    | The shared secret key used to sign and verify JWT tokens.
    | Must match the JWT_SECRET in the Node.js backend's environment.
    |
    */
    'secret' => env('JWT_SECRET', ''),
];
