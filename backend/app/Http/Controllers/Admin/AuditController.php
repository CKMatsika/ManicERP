<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\AuditEvent;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = AuditEvent::query()->orderByDesc('chain_index');

        if (! empty($filters['action'])) {
            $query->where('action', 'like', '%'.$filters['action'].'%');
        }

        if (! empty($filters['from'])) {
            $query->whereDate('occurred_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('occurred_at', '<=', $filters['to']);
        }

        $events = $query->paginate(25)->withQueryString()->through(fn (AuditEvent $event) => [
            'id' => $event->id,
            'chain_index' => $event->chain_index,
            'action' => $event->action,
            'module' => $event->module,
            'actor' => $event->actor_name,
            'role' => $event->actor_role,
            'subject_type' => $event->subject_type,
            'subject_id' => $event->subject_id,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
            'hash' => substr($event->hash, 0, 16).'...',
            'metadata' => $event->metadata,
        ]);

        return Inertia::render('Admin/Audit', [
            'events' => $events,
            'filters' => $filters,
        ]);
    }

    public function verify(AuditLogger $audit)
    {
        $result = $audit->verify();

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['ok']
                ? "Audit chain verified: {$result['checked']} events."
                : "Audit chain broken at event {$result['broken_at']}.",
        );
    }
}
