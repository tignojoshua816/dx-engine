<?php
/**
 * DX-Engine — Router
 * -----------------------------------------------------------------------
 * Maps incoming HTTP requests to registered DX controllers.
 *
 * Usage in public/api/dx.php:
 *
 *   $router = new Router();
 *   $router->register('admission_case', \App\DX\AdmissionDX::class);
 *   $router->dispatch($_REQUEST['dx'] ?? '');
 */

namespace DXEngine\Core;

class Router
{
    /** @var array<string, class-string<DXController>> */
    private array $registry = [];

    /** Register a DX identifier => controller class. */
    public function register(string $dxId, string $controllerClass): self
    {
        $this->registry[$dxId] = $controllerClass;
        return $this;
    }

    /** Resolve and dispatch the request. */
    public function dispatch(string $dxId): void
    {
        // CORS (adjust origins in production)
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        if (!isset($this->registry[$dxId])) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => "Unknown DX: {$dxId}"]);
            exit;
        }

        $class      = $this->registry[$dxId];
        $controller = new $class();

        // Merge GET + POST; decode JSON body if Content-Type is application/json
        $params = array_merge($_GET, $_POST);
        $raw    = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $params = array_merge($params, $decoded);
            }
        }

        $context = [
            'method'          => $_SERVER['REQUEST_METHOD'],
            'params'          => $params,
            'session'         => $_SESSION ?? [],
            'files'           => $_FILES ?? [],
            // Resolved dynamically by config/app.php via dx.php bootstrap.
            // DX controllers read this from $context['dx_api_endpoint'] so
            // post_endpoint is never hardcoded for a specific subfolder.
            'dx_api_endpoint' => $_SERVER['DX_API_ENDPOINT'] ?? '/dx-engine/public/api/dx.php',
        ];

        $controller->dispatch($context);
    }
}
