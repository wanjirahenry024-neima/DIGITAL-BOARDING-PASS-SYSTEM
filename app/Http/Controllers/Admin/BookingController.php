<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'booking_status' => ['nullable', Rule::in(Booking::STATUSES)],
        ]);

        $bookings = Booking::query()
            ->with(['passenger', 'flight'])
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('booking_reference', 'like', "%{$search}%")
                        ->orWhereHas('passenger', function ($query) use ($search): void {
                            $query->where('full_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('flight', fn ($query) => $query->where('flight_number', 'like', "%{$search}%"));
                });
            })
            ->when($filters['booking_status'] ?? null, fn ($query, string $status) => $query->where('booking_status', $status))
            ->orderByDesc('booking_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.bookings.index', [
            'bookings' => $bookings,
            'statuses' => Booking::STATUSES,
        ]);
    }

    public function show(Booking $booking): View
    {
        $booking->load(['passenger', 'flight']);

        return view('admin.bookings.show', compact('booking'));
    }

    public function cancel(Request $request, Booking $booking, BookingService $bookingService): RedirectResponse
    {
        $bookingService->cancel($booking, $request->user());

        return redirect()->route('admin.bookings.show', $booking)->with('status', 'Booking cancelled.');
    }
}
