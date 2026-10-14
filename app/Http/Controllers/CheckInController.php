<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCheckInRequest;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Services\CheckInService;
use App\Services\QrCodeGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckInController extends Controller
{
    public function show(Request $request, Booking $booking, CheckInService $checkInService): View
    {
        $booking = $request->user()->bookings()
            ->with(['flight', 'passenger', 'checkIn.boardingPass'])
            ->findOrFail($booking->getKey());

        return view('check-ins.show', [
            'booking' => $booking,
            'unavailableReason' => $checkInService->unavailableReason($booking),
        ]);
    }

    public function store(
        StoreCheckInRequest $request,
        Booking $booking,
        CheckInService $checkInService,
        QrCodeGenerationService $qrCodeGenerationService,
    ): RedirectResponse {
        $passenger = $request->user();
        $booking = $passenger->bookings()->findOrFail($booking->getKey());
        $checkIn = $checkInService->create($booking, $passenger, $qrCodeGenerationService);

        return redirect()->route('check-ins.confirmation', $checkIn);
    }

    public function confirmation(Request $request, CheckIn $checkIn): View
    {
        $checkIn = $request->user()->checkIns()
            ->with(['booking', 'boardingPass', 'flight', 'passenger'])
            ->findOrFail($checkIn->getKey());

        return view('check-ins.confirmation', compact('checkIn'));
    }
}
