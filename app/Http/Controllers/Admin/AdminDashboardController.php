<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoardingPass;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Flight;
use App\Models\User;
use App\Models\VerificationRecord;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        $statistics = [
            'passengers' => User::query()->where('role', 'passenger')->count(),
            'staff' => User::query()->where('role', 'staff')->count(),
            'flights' => Flight::query()->count(),
            'bookings' => Booking::query()->count(),
            'check_ins' => CheckIn::query()->count(),
            'active_boarding_passes' => BoardingPass::query()->where('pass_status', BoardingPass::STATUS_ACTIVE)->count(),
            'used_boarding_passes' => BoardingPass::query()->where('pass_status', BoardingPass::STATUS_USED)->count(),
            'invalid_verifications' => VerificationRecord::query()
                ->where('result', VerificationRecord::RESULT_INVALID)
                ->count(),
            'today_boarding_activity' => BoardingPass::query()->whereDate('used_at', today())->count(),
            'offline_verifications' => VerificationRecord::query()
                ->where('verification_method', VerificationRecord::METHOD_OFFLINE)
                ->count(),
            'pending_synchronization' => VerificationRecord::query()
                ->whereIn('sync_status', [
                    VerificationRecord::SYNC_PENDING,
                    VerificationRecord::SYNC_SYNCING,
                    VerificationRecord::SYNC_FAILED,
                ])
                ->count(),
        ];

        $recentVerifications = VerificationRecord::query()
            ->with(['staff:id,full_name', 'flight:id,flight_number'])
            ->latest('verified_at')
            ->latest('id')
            ->limit(8)
            ->get(['id', 'staff_id', 'flight_id', 'verification_reference', 'result', 'verification_method', 'verified_at']);

        return view('admin.dashboard', compact('statistics', 'recentVerifications'));
    }
}
