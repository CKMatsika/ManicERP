<?php

namespace App\Http\Controllers\Licensing;

use App\Http\Controllers\Controller;
use App\Modules\Licensing\Services\LicenceNotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request, LicenceNotificationService $notifications)
    {
        $items = $notifications->inbox($request->user());
        if ($request->expectsJson()) {
            return response()->json(['data' => $items]);
        }

        return Inertia::render('Licensing/Notifications', ['notifications' => $items, 'portal' => $request->routeIs('portal.*')]);
    }

    public function read(Request $request, string $notification, LicenceNotificationService $notifications)
    {
        $item = $notifications->markRead($request->user(), $notification);
        if ($request->expectsJson()) {
            return response()->json(['data' => ['id' => $item->id, 'read_at' => $item->read_at?->toIso8601String()]]);
        }

        return back()->with('success', 'Notification marked as read.');
    }
}
