<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BoardingPass;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BoardingPassController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'pass_status' => ['nullable', Rule::in(BoardingPass::STATUSES)],
        ]);

        $boardingPasses = BoardingPass::query()
            ->with(['passenger:id,full_name', 'flight:id,flight_number,departure_date', 'booking:id,booking_reference'])
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('pass_reference', 'like', "%{$search}%")
                        ->orWhereHas('passenger', fn ($passenger) => $passenger->where('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('flight', fn ($flight) => $flight->where('flight_number', 'like', "%{$search}%"))
                        ->orWhereHas('booking', fn ($booking) => $booking->where('booking_reference', 'like', "%{$search}%"));
                });
            })
            ->when($filters['pass_status'] ?? null, fn ($query, string $status) => $query->where('pass_status', $status))
            ->latest('issued_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.boarding-passes.index', [
            'boardingPasses' => $boardingPasses,
            'statuses' => BoardingPass::STATUSES,
        ]);
    }
}
