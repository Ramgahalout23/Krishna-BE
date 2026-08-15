<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    | Production note:
    | In production, the SPA is served from Laravel's public/ directory, so
    | the frontend and API are on the same origin and CORS is not needed
    | for normal operation. The wildcard origin below is safe for development
    | (Vite dev server handles its own CORS via proxy).
    |
    | For production with a separate frontend domain, set the CORS_ALLOWED_ORIGINS
    | environment variable to a comma-separated list of allowed origins:
    |   CORS_ALLOWED_ORIGINS=https://example.com,https://www.example.com
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
     | Restrict allowed origins via .env for production.
     | Defaults to '*' for development (safe because SPA + API are same-origin).
     */
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 86400,

    /*
     | Credentials are only needed when frontend and backend are on different
     | origins (e.g. separate subdomains or during local development with
     | a custom proxy setup). Set supports_credentials=true and use specific
     | origins (not '*') in CORS_ALLOWED_ORIGINS.
     */
    'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', false),

];
