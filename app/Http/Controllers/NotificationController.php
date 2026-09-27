<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\CompletenessService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends BaseController
{
    public function __construct(
        AuditService $audit,
        CompletenessService $completeness,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($audit, $completeness);
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $list = $user->notifications()
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->paginate(20);

        return view('notifications.index', [
            'notifications' => $list,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Opening a notification marks it read and forwards to the resource, so
     * the unread badge in the topbar stays honest.
     */
    public function read(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->findOrFail($notification);

        $item->markAsRead();

        return redirect()->to($this->notifications->destinationFor($item->data, $request->user()));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        $this->audit->log('notifications.read_all', $request->user(), 'Menandai semua notifikasi sebagai dibaca');

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
