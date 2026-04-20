<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Services\PlatformAutomationService;

class AutomationController extends Controller
{
    public function run(Request $request): void
    {
        $token = trim((string) $request->query('token', ''));
        $task = trim((string) $request->query('task', ''));
        $service = new PlatformAutomationService();

        if (!$service->validToken($token)) {
            throw new HttpException(403, 'Invalid automation token.');
        }

        header('Content-Type: application/json');
        echo json_encode($service->dispatchScheduled($task !== '' ? $task : null));
    }

    public function manualRun(Request $request): void
    {
        $payload = [
            'task' => trim((string) $request->input('task', '')),
        ];
        $errors = Validator::validate($payload, [
            'task' => 'required|in:billing_cycle,daily_summary,backup_snapshot',
        ]);

        if ($errors !== []) {
            Session::flash('error', 'Choose a valid automation task first.');
            $this->redirect('platform/settings');
        }

        $result = (new PlatformAutomationService())->dispatchManual($payload['task'], Auth::id());
        $taskResult = $result['results'][$payload['task']] ?? [];
        $status = (string) ($taskResult['status'] ?? 'failed');
        $message = $status === 'succeeded'
            ? (string) (($taskResult['summary']['message'] ?? '') ?: 'Automation task completed successfully.')
            : (string) (($taskResult['message'] ?? 'Automation task failed.'));

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'run_platform_automation',
            entityType: 'platform_automation',
            entityId: null,
            description: 'Ran platform automation task ' . $payload['task'] . ' manually.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash($status === 'succeeded' ? 'success' : 'error', $message);
        $this->redirect('platform/settings');
    }
}
