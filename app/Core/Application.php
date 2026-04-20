<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

class Application
{
    private Router $router;

    public function __construct()
    {
        $this->router = new Router();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function run(): void
    {
        try {
            $request = Request::capture();
            $csrfExceptPaths = [
                '/billing/payments/paystack/webhook',
            ];

            if (
                $request->isStateChanging()
                && !in_array($request->path(), $csrfExceptPaths, true)
                && !Csrf::validate($request->input('_token'))
            ) {
                throw new HttpException(419, 'The form expired. Refresh the page and try again.');
            }

            $this->router->dispatch($request);
        } catch (HttpException $exception) {
            $this->renderError(
                statusCode: $exception->statusCode,
                message: $exception->getMessage()
            );
        } catch (Throwable $exception) {
            try {
                $errorId = 'ERR-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
            } catch (Throwable) {
                $errorId = 'ERR-' . strtoupper(substr(sha1((string) microtime(true)), 0, 12));
            }

            Logger::error('Unhandled application exception', [
                'error_id' => $errorId,
                'type' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $message = (bool) config('app.debug', false)
                ? $exception->getMessage()
                : 'An unexpected error occurred. Reference: ' . $errorId;

            $this->renderError(statusCode: 500, message: $message);
        }
    }

    private function renderError(int $statusCode, string $message): void
    {
        http_response_code($statusCode);

        $title = match ($statusCode) {
            403 => 'Access denied',
            404 => 'Page not found',
            419 => 'Session expired',
            500 => 'Server error',
            default => 'Application error',
        };

        $view = app_path('Views/errors/' . $statusCode . '.php');

        if (!is_file($view)) {
            $view = app_path('Views/errors/generic.php');
        }

        require $view;
    }
}
