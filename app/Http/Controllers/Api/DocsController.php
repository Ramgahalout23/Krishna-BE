<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\OpenApi\SpecBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DocsController extends Controller
{
    /**
     * Return the OpenAPI 3.0 JSON specification.
     * Built by aggregating all domain-specific section files.
     */
    public function json(): JsonResponse
    {
        return response()->json(SpecBuilder::build());
    }

    /**
     * Serve a self-contained Swagger UI page using CDN assets.
     */
    public function ui(): Response
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>LUXE API Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css" />
    <style>
        body { margin: 0; background: #f5f5f5; }
        .swagger-ui .topbar { display: none; }
        .swagger-ui .info .title { color: #1a1a2e; }
        .swagger-ui .btn.authorize { background: #1a1a2e; border-color: #1a1a2e; }
        .swagger-ui .opblock-tag { border-bottom: 2px solid #1a1a2e; }
        .swagger-ui .model-box { background: #fafafa; border-radius: 4px; }
        .swagger-ui .model { font-size: 13px; }
        .swagger-ui .model-container { background: #fff; border-radius: 8px; }
        .swagger-ui .responses-inner h4, .swagger-ui .responses-inner h5 { font-weight: 700; }
        .swagger-ui .parameter__name { font-weight: 600; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-standalone-preset.js"></script>
    <script>
        SwaggerUIBundle({
            url: '/api/v1/docs/json',
            dom_id: '#swagger-ui',
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset,
            ],
            layout: 'StandaloneLayout',
            docExpansion: 'list',
            defaultModelRendering: 'model',
            defaultModelsExpandDepth: 1,
            defaultModelExpandDepth: 1,
            tryItOutEnabled: true,
            supportedSubmitMethods: ['get', 'post', 'put', 'patch', 'delete'],
            filter: true,
            showExtensions: true,
            showCommonExtensions: true,
            deepLinking: true,
            displayRequestDuration: true,
            requestSnippetsEnabled: true,
            persistAuthorization: true,
        });
    </script>
</body>
</html>
HTML;

        return response($html, 200, ['Content-Type' => 'text/html']);
    }
}
