<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\CheckInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $bookings = $request->user()->bookings()
            ->with('flight')
            ->orderByDesc('booking_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('bookings.index', compact('bookings'));
    }

    public function store(StoreBookingRequest $request, BookingService $bookingService): RedirectResponse
    {
        $booking = $bookingService->create(
            $request->user(),
            $request->integer('flight_id'),
            $request->integer('number_of_passengers'),
        );

        return redirect()->route('bookings.confirmation', $booking);
    }

    public function confirmation(Request $request, Booking $booking): View
    {
        $booking = $request->user()->bookings()
            ->with('flight')
            ->findOrFail($booking->getKey());

        return view('bookings.confirmation', compact('booking'));
    }

    public function show(Request $request, Booking $booking, CheckInService $checkInService): View
    {
        $booking = $request->user()->bookings()
            ->with(['flight', 'checkIn.boardingPass'])
            ->findOrFail($booking->getKey());
        $flight = $booking->flight;
        $canCancel = $booking->booking_status === Booking::STATUS_CONFIRMED
            && $booking->checkIn === null
            && $flight->departure_date->greaterThanOrEqualTo(today())
            && ! in_array($flight->status, ['Departed', 'Completed'], true);
        $canCheckIn = $checkInService->unavailableReason($booking) === null;
        $checkIn = $booking->checkIn;

        return view('bookings.show', compact('booking', 'canCancel', 'canCheckIn', 'checkIn'));
    }

    public function cancel(Request $request, Booking $booking, BookingService $bookingService): RedirectResponse
    {
        $booking = $request->user()->bookings()->findOrFail($booking->getKey());
        $bookingService->cancel($booking, $request->user());

        return redirect()->route('bookings.show', $booking)->with('status', 'Booking cancelled.');
    }
}
