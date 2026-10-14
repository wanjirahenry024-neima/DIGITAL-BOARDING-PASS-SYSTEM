<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\BoardingPassVerification;
use App\Services\GateSyncService;
use Illuminate\View\View;

class StaffDashboardController extends Controller
{
    public function __invoke(GateSyncService $gateSyncService): View
    {
        $todayVerifications = BoardingPassVerification::query()
            ->whereDate('verified_at', today());

        $statistics = [
            'today' => (clone $todayVerifications)->count(),
            'approved' => (clone $todayVerifications)
                ->where('status', BoardingPassVerification::STATUS_USED)
                ->count(),
            'valid_pending' => (clone $todayVerifications)
                ->where('status', BoardingPassVerification::STATUS_VALID)
                ->count(),
            'failed' => (clone $todayVerifications)
                ->whereIn('status', [
                    BoardingPassVerification::STATUS_EXPIRED,
                    BoardingPassVerification::STATUS_CANCELLED,
                    BoardingPassVerification::STATUS_INVALID,
                    BoardingPassVerification::STATUS_NOT_FOUND,
                ])
                ->count(),
        ];

        $recentVerifications = BoardingPassVerification::query()
            ->with(['boardingPass.passenger', 'boardingPass.flight', 'staff'])
            ->latest('verified_at')
            ->latest('id')
            ->limit(10)
            ->get();

        return view('staff.dashboard', [
            'recentVerifications' => $recentVerifications,
            'statistics' => $statistics,
            'gateTerminalMode' => (bool) config('services.gate_terminal.enabled'),
            'gateStatus' => $gateSyncService->status(),
        ]);
    }
}
