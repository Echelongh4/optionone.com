<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function index(Request $request): void
    {
        $notificationModel = new Notification();
        $filters = $this->filters($request);

        $this->render('notifications/index', [
            'title' => 'Notifications',
            'breadcrumbs' => ['Dashboard', 'Notifications'],
            'notifications' => $notificationModel->listForUser(Auth::id(), $this->branchId(), $filters),
            'unreadCount' => $notificationModel->unreadCount(Auth::id(), $this->branchId()),
            'summary' => $notificationModel->summaryForUser(Auth::id(), $this->branchId()),
            'types' => $notificationModel->typesForUser(Auth::id(), $this->branchId()),
            'filters' => $filters,
        ]);
    }

    public function markAllRead(Request $request): void
    {
        (new Notification())->markAllRead(Auth::id(), $this->branchId());
        $this->redirectBackToCenter($request, 'Notifications marked as read.');
    }

    public function markRead(Request $request): void
    {
        $notificationId = (int) $request->input('id');
        $updated = (new Notification())->markRead($notificationId, Auth::id(), $this->branchId());

        $this->redirectBackToCenter(
            $request,
            $updated ? 'Notification marked as read.' : 'Notification could not be updated.',
            $updated ? 'success' : 'warning'
        );
    }

    public function markUnread(Request $request): void
    {
        $notificationId = (int) $request->input('id');
        $updated = (new Notification())->markUnread($notificationId, Auth::id(), $this->branchId());

        $this->redirectBackToCenter(
            $request,
            $updated ? 'Notification marked as unread.' : 'Notification could not be updated.',
            $updated ? 'success' : 'warning'
        );
    }

    public function open(Request $request): void
    {
        $notificationModel = new Notification();
        $notification = $notificationModel->findForUser((int) $request->query('id'), Auth::id(), $this->branchId());

        if ($notification === null) {
            Session::flash('warning', 'Notification could not be found.');
            $this->redirect('notifications');
        }

        if (!(bool) ($notification['is_read'] ?? false)) {
            $notificationModel->markRead((int) $notification['id'], Auth::id(), $this->branchId());
        }

        $target = trim((string) ($notification['link_url'] ?? ''));
        $this->redirect($target !== '' ? normalize_app_relative_path($target) : 'notifications');
    }

    private function filters(Request $request): array
    {
        $status = trim((string) $request->query('status', 'all'));
        $allowedStatuses = ['all', 'unread', 'read'];

        return [
            'status' => in_array($status, $allowedStatuses, true) ? $status : 'all',
            'type' => trim((string) $request->query('type', '')),
        ];
    }

    private function redirectBackToCenter(Request $request, string $message, string $flashKey = 'success'): never
    {
        Session::flash($flashKey, $message);

        $query = [];
        $status = trim((string) $request->input('status', $request->query('status', 'all')));
        $type = trim((string) $request->input('type', $request->query('type', '')));

        if ($status !== '' && $status !== 'all') {
            $query['status'] = $status;
        }

        if ($type !== '') {
            $query['type'] = $type;
        }

        $url = 'notifications';
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $this->redirect($url);
    }

    private function branchId(): int
    {
        return (int) (current_branch_id() ?? 0);
    }
}
