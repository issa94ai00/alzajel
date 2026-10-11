<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(Permissions::ACTIVITY_VIEW), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', Rule::in(array_keys(ActivityLogger::EVENT_LABELS))],
            'causer_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $logs = ActivityLog::query()
            ->with('causer:id,name')
            ->search($filters['search'] ?? null)
            ->event($filters['event'] ?? null)
            ->when($filters['causer_id'] ?? null, fn ($q, $id) => $q->where('causer_id', $id))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'event_label' => ActivityLogger::label($log->event),
                'description' => $log->description,
                'causer' => $log->causer?->name ?? $log->causer_name,
                'causer_id' => $log->causer_id,
                'subject_id' => $log->subject_id,
                'subject_type' => $log->subject_type ? class_basename($log->subject_type) : null,
                'ip_address' => $log->ip_address,
                'properties' => $log->properties,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Activity/Index', [
            'logs' => $logs,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'event' => $filters['event'] ?? '',
                'causer_id' => $filters['causer_id'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
            ],
            'events' => ActivityLogger::EVENT_LABELS,
            'causers' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
