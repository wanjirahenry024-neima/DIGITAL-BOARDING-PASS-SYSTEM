<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['passenger', 'staff', 'admin'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $users = User::query()
            ->select(['id', 'full_name', 'email', 'phone', 'role', 'status', 'created_at'])
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('full_name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => ['passenger', 'staff', 'admin'],
            'statuses' => ['active', 'inactive'],
        ]);
    }

    public function update(Request $request, User $user, AuditLogService $auditLogService): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['passenger', 'staff', 'admin'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        if (
            $user->is($request->user())
            && ($validated['role'] !== 'admin' || $validated['status'] !== 'active')
        ) {
            return back()->withErrors(['user' => 'You cannot remove administrator access or deactivate your own account.']);
        }

        DB::transaction(function () use ($user, $validated, $auditLogService, $request): void {
            $previous = ['role' => $user->role, 'status' => $user->status];
            $user->update($validated);

            $auditLogService->record(
                AuditLog::EVENT_ADMINISTRATOR_CHANGE,
                $request->user(),
                $user,
                'Administrator updated a user account.',
                ['action' => 'user_updated', 'from' => $previous, 'to' => $validated],
            );
        });

        return back()->with('status', 'User account updated.');
    }
}
