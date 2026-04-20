<?php

declare(strict_types=1);

namespace App\Core;

class Controller
{
    protected const SUBMISSION_GUARD_TTL = 7200;

    protected function render(string $view, array $data = [], string $layout = 'app'): void
    {
        $viewFile = app_path('Views/' . $view . '.php');

        if (!is_file($viewFile)) {
            throw new HttpException(500, 'View not found: ' . $view);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        // If this was requested via AJAX (e.g. modal load), return only the view
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if ($requestedWith !== '' && strtolower((string) $requestedWith) === 'xmlhttprequest') {
            echo $content;
            return;
        }

        $layoutFile = app_path('Views/layouts/' . $layout . '.php');

        if (!is_file($layoutFile)) {
            throw new HttpException(500, 'Layout not found: ' . $layout);
        }

        require $layoutFile;
    }

    protected function redirect(string $path): never
    {
        Response::redirect($path);
    }

    protected function issueSubmissionKey(string $scope): string
    {
        $tokens = $this->pruneSubmissionEntries((array) Session::get('_submission_tokens.' . $scope, []));
        $key = bin2hex(random_bytes(16));
        $tokens[$key] = time();
        Session::put('_submission_tokens.' . $scope, $tokens);

        return $key;
    }

    protected function reuseOrIssueSubmissionKey(string $scope, string $requestedKey): string
    {
        if ($requestedKey !== '' && $this->isIssuedSubmissionKey($scope, $requestedKey)) {
            return $requestedKey;
        }

        return $this->issueSubmissionKey($scope);
    }

    protected function isIssuedSubmissionKey(string $scope, string $requestedKey): bool
    {
        if ($requestedKey === '') {
            return false;
        }

        $tokens = $this->pruneSubmissionEntries((array) Session::get('_submission_tokens.' . $scope, []));
        Session::put('_submission_tokens.' . $scope, $tokens);

        return array_key_exists($requestedKey, $tokens);
    }

    protected function processedSubmission(string $scope, string $requestedKey): ?array
    {
        if ($requestedKey === '') {
            return null;
        }

        $processed = $this->pruneSubmissionEntries((array) Session::get('_processed_submissions.' . $scope, []));
        Session::put('_processed_submissions.' . $scope, $processed);
        $payload = $processed[$requestedKey] ?? null;

        return is_array($payload) ? $payload : null;
    }

    protected function rememberProcessedSubmission(string $scope, string $requestedKey, array $payload): void
    {
        if ($requestedKey === '') {
            return;
        }

        $processed = $this->pruneSubmissionEntries((array) Session::get('_processed_submissions.' . $scope, []));
        $processed[$requestedKey] = array_merge($payload, ['stored_at' => time()]);
        Session::put('_processed_submissions.' . $scope, $processed);

        $tokens = $this->pruneSubmissionEntries((array) Session::get('_submission_tokens.' . $scope, []));
        unset($tokens[$requestedKey]);
        Session::put('_submission_tokens.' . $scope, $tokens);
    }

    protected function respondWithStoredSubmission(Request $request, array $payload): never
    {
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode($payload);
            exit;
        }

        $flashType = (string) ($payload['flash_type'] ?? 'success');
        $flashMessage = (string) ($payload['flash_message'] ?? $payload['message'] ?? '');
        if ($flashMessage !== '') {
            Session::flash($flashType === 'error' ? 'error' : 'success', $flashMessage);
        }

        $this->redirect((string) ($payload['redirect_path'] ?? ''));
    }

    protected function pruneSubmissionEntries(array $entries): array
    {
        $cutoff = time() - self::SUBMISSION_GUARD_TTL;

        return array_filter($entries, static function (mixed $value) use ($cutoff): bool {
            if (is_array($value)) {
                return (int) ($value['stored_at'] ?? 0) >= $cutoff;
            }

            return (int) $value >= $cutoff;
        });
    }
}
