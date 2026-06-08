<?php

// router.php - Used by PHP Built-in Server for phpswag watch
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$specFile = getenv('PHPSWAG_SPEC_FILE') ?: 'swagger.yaml';
$changeFile = getenv('PHPSWAG_CHANGE_FILE') ?: __DIR__ . '/../../.phpswag-changed';

// Short polling endpoint to check for spec changes
if ($requestPath === '/check-changes') {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $lastSeen = (int)($_GET['last'] ?? 0);
    $changed = false;
    $mtime = 0;

    clearstatcache(true, $changeFile);
    if (file_exists($changeFile)) {
        $mtime = filemtime($changeFile);
        if ($mtime > $lastSeen) {
            $changed = true;
        }
    }

    echo json_encode([
        'changed' => $changed,
        'last' => $mtime ?: time(),
    ]);
    exit;
}

// Serve the generated swagger file
if ($requestPath === '/swagger.yaml' || $requestPath === '/swagger.json') {
    if (file_exists($specFile)) {
        header('Content-Type: ' . (str_ends_with($specFile, '.json') ? 'application/json' : 'text/yaml'));
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        readfile($specFile);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo "Specification file not found.";
    }
    exit;
}

// Serve Swagger UI HTML
if ($requestPath === '/' || $requestPath === '/index.html') {
    header('Content-Type: text/html');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="description" content="phpswag Live Preview" />
        <title>phpswag Live Preview</title>
        <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
        <style>
            html { box-sizing: border-box; overflow: -y-scroll; }
            *, *:before, *:after { box-sizing: inherit; }
            body { margin: 0; background: #fafafa; }
        </style>
    </head>
    <body>
        <div id="swagger-ui"></div>
        <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
        <script>
            window.onload = () => {
                const specUrl = '<?= str_ends_with($specFile, ".json") ? "/swagger.json" : "/swagger.yaml" ?>';
                const ui = SwaggerUIBundle({
                    url: specUrl,
                    dom_id: '#swagger-ui',
                    deepLinking: true,
                    presets: [
                        SwaggerUIBundle.presets.apis,
                        SwaggerUIBundle.SwaggerUIStandalonePreset
                    ],
                    layout: "BaseLayout"
                });
                window.ui = ui;

                // Set up short-polling for live-reload
                let lastSeen = Math.floor(Date.now() / 1000);
                setInterval(() => {
                    fetch('/check-changes?last=' + lastSeen)
                        .then(response => response.json())
                        .then(data => {
                            if (data.changed) {
                                console.log('Spec changed, reloading Swagger UI...');
                                lastSeen = data.last;
                                ui.actions.downloadSpec(ui.specSelectors.url());
                            }
                        })
                        .catch(err => console.error('Error checking for changes:', err));
                }, 1000); // Check every 1 second
            };
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Fallback to static files if any
return false;
