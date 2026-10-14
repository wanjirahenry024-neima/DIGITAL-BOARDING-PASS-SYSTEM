<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $events = [
            AuditLog::EVENT_LOGIN,
            AuditLog::EVENT_LOGIN_FAILED,
            AuditLog::EVENT_LOGOUT,
            AuditLog::EVENT_BOOKING_CREATED,
            AuditLog::EVENT_BOOKING_CANCELLED,
            AuditLog::EVENT_CHECK_IN,
            AuditLog::EVENT_BOARDING_PASS_CREATED,
            AuditLog::EVENT_QR_VERIFICATION,
            AuditLog::EVENT_FAILED_VERIFICATION,
            AuditLog::EVENT_BOARDING_APPROVED,
            AuditLog::EVENT_ADMINISTRATOR_CHANGE,
        ];
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', Rule::in($events)],
            'occurred_date' => ['nullable', 'date'],
        ]);

        $auditLogs = AuditLog::query()
            ->with('actor')
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('event', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('subject_id', 'like', "%{$search}%")
                        ->orWhereHas('actor', fn ($actor) => $actor->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['event'] ?? null, fn ($query, string $event) => $query->where('event', $event))
            ->when($filters['occurred_date'] ?? null, fn ($query, string $date) => $query->whereDate('occurred_at', $date))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.audit-logs.index', compact('auditLogs', 'events'));
    }
}
