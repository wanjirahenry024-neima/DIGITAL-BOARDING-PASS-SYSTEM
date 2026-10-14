<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VerificationRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VerificationRecordController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'result' => ['nullable', Rule::in(VerificationRecord::RESULTS)],
            'verification_method' => ['nullable', Rule::in(VerificationRecord::METHODS)],
            'sync_status' => ['nullable', Rule::in([
                VerificationRecord::SYNC_PENDING,
                VerificationRecord::SYNC_SYNCING,
                VerificationRecord::SYNC_SYNCED,
                VerificationRecord::SYNC_FAILED,
                VerificationRecord::SYNC_CONFLICT,
            ])],
            'verified_date' => ['nullable', 'date'],
        ]);

        $verificationRecords = VerificationRecord::query()
            ->with([
                'staff:id,full_name',
                'passenger:id,full_name',
                'flight:id,flight_number',
            ])
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('verification_reference', 'like', "%{$search}%")
                        ->orWhere('gate', 'like', "%{$search}%")
                        ->orWhereHas('staff', fn ($staff) => $staff->where('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('passenger', fn ($passenger) => $passenger->where('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('flight', fn ($flight) => $flight->where('flight_number', 'like', "%{$search}%"));
                });
            })
            ->when($filters['result'] ?? null, fn ($query, string $result) => $query->where('result', $result))
            ->when($filters['verification_method'] ?? null, fn ($query, string $method) => $query->where('verification_method', $method))
            ->when($filters['sync_status'] ?? null, fn ($query, string $status) => $query->where('sync_status', $status))
            ->when($filters['verified_date'] ?? null, fn ($query, string $date) => $query->whereDate('verified_at', $date))
            ->latest('verified_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.verification-records.index', [
            'verificationRecords' => $verificationRecords,
            'results' => VerificationRecord::RESULTS,
            'methods' => VerificationRecord::METHODS,
            'syncStatuses' => [
                VerificationRecord::SYNC_PENDING,
                VerificationRecord::SYNC_SYNCING,
                VerificationRecord::SYNC_SYNCED,
                VerificationRecord::SYNC_FAILED,
                VerificationRecord::SYNC_CONFLICT,
            ],
        ]);
    }
}
