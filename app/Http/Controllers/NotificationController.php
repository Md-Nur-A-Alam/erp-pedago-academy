<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['show' => ['nullable', Rule::in(['all', 'unread'])]]);
        $show = $filters['show'] ?? 'all';
        $user = $request->user();

        return view('notifications.index', [
            'show' => $show,
            'unread' => $user->unreadNotifications()->count(),
            'notifications' => ($show === 'unread' ? $user->unreadNotifications() : $user->notifications())->paginate(25)->withQueryString(),
        ]);
    }

    /**
     * Mark one notification read and go to what it is about.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->findOrFail($notification);
        $item->markAsRead();

        return redirect($item->data['url'] ?? route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }

    /**
     * Unread total, polled by the bell.
     */
    public function count(Request $request): JsonResponse
    {
        return response()->json(['unread' => $request->user()->unreadNotifications()->count()]);
    }
}
