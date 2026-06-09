<?php

namespace PhpSwag\Bridges\Laravel;

use Illuminate\Support\ServiceProvider;
use PhpSwag\Bridges\Laravel\Commands\GenerateCommand;
use Illuminate\Support\Facades\Route;

class PhpSwagServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/phpswag.php' => config_path('phpswag.php'),
            ], 'phpswag-config');
        }

        $this->registerRoutes();
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/phpswag.php',
            'phpswag'
        );

        $this->commands([
            GenerateCommand::class,
        ]);
    }

    /**
     * Register Swagger UI routes.
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $enabled = config('phpswag.swagger_ui', true);
        if (!$enabled) {
            return;
        }

        $path = config('phpswag.swagger_ui_path', '/api/docs');
        $specOutput = config('phpswag.output');

        // Try to figure out relative URL for swagger spec file
        // e.g. if output is public_path('swagger.yaml'), URL is /swagger.yaml
        $specUrl = '/swagger.yaml';
        if ($specOutput && is_string($specOutput)) {
            $publicPath = public_path();
            if (str_starts_with($specOutput, $publicPath)) {
                $relative = substr($specOutput, strlen($publicPath));
                $specUrl = '/' . ltrim(str_replace('\\', '/', $relative), '/');
            }
        }

        Route::get($path, function () use ($specUrl) {
            $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>API Documentation</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js" crossorigin></script>
    <script>
        window.onload = () => {
            window.ui = SwaggerUIBundle({
                url: '{$specUrl}',
                dom_id: '#swagger-ui',
            });
        };
    </script>
</body>
</html>
HTML;
            return response($html, 200, ['Content-Type' => 'text/html']);
        });
    }
}
