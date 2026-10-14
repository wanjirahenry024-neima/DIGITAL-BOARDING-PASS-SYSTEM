<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FlightRequest;
use App\Models\AuditLog;
use App\Models\Flight;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PassengerNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FlightController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Flight::STATUSES)],
            'departure_date' => ['nullable', 'date'],
        ]);

        $flights = Flight::query()
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('flight_number', 'like', "%{$search}%")
                        ->orWhere('airline', 'like', "%{$search}%")
                        ->orWhere('departure_airport', 'like', "%{$search}%")
                        ->orWhere('arrival_airport', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['departure_date'] ?? null, fn ($query, string $date) => $query->whereDate('departure_date', $date))
            ->orderBy('departure_date')
            ->orderBy('departure_time')
            ->paginate(15)
            ->withQueryString();

        return view('flights.admin.index', [
            'flights' => $flights,
            'statuses' => Flight::STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('flights.admin.create', ['statuses' => Flight::STATUSES]);
    }

    public function store(FlightRequest $request, AuditLogService $auditLogService): RedirectResponse
    {
        $flight = DB::transaction(function () use ($request, $auditLogService): Flight {
            $flight = Flight::create($request->validated());
            $this->recordAdministratorChange(
                $auditLogService,
                $request->user(),
                $flight,
                'flight_created',
                ['flight_number' => $flight->flight_number],
            );

            return $flight;
        });

        return redirect()->route('admin.flights.show', $flight)->with('status', 'Flight created.');
    }

    public function show(Flight $flight): View
    {
        return view('flights.admin.show', compact('flight'));
    }

    public function edit(Flight $flight): View
    {
        return view('flights.admin.edit', [
            'flight' => $flight,
            'statuses' => Flight::STATUSES,
        ]);
    }

    public function update(
        FlightRequest $request,
        Flight $flight,
        AuditLogService $auditLogService,
        PassengerNotificationService $passengerNotificationService,
    ): RedirectResponse {
        DB::transaction(function () use ($request, $flight, $auditLogService, $passengerNotificationService): void {
            $previous = $flight->getRawOriginal();
            $flight->update($request->validated());
            $flight->refresh();
            $this->recordAdministratorChange(
                $auditLogService,
                $request->user(),
                $flight,
                'flight_updated',
                ['changes' => $this->flightChanges($previous, $flight->getRawOriginal())],
            );

            if (($previous['status'] ?? null) !== $flight->getRawOriginal()['status']) {
                $passengerNotificationService->flightStatusChanged($flight, $flight->status);
            }
        });

        return redirect()->route('admin.flights.show', $flight)->with('status', 'Flight updated.');
    }

    public function updateStatus(
        Request $request,
        Flight $flight,
        AuditLogService $auditLogService,
        PassengerNotificationService $passengerNotificationService,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Flight::STATUSES)],
        ]);

        DB::transaction(function () use (
            $request,
            $flight,
            $auditLogService,
            $passengerNotificationService,
            $validated,
        ): void {
            $previousStatus = $flight->status;
            $flight->update($validated);
            $this->recordAdministratorChange(
                $auditLogService,
                $request->user(),
                $flight,
                'flight_status_updated',
                ['status' => ['from' => $previousStatus, 'to' => $flight->status]],
            );

            if ($previousStatus !== $flight->status) {
                $passengerNotificationService->flightStatusChanged($flight, $flight->status);
            }
        });

        return back()->with('status', 'Flight status updated.');
    }

    public function destroy(Request $request, Flight $flight, AuditLogService $auditLogService): RedirectResponse
    {
        DB::transaction(function () use ($request, $flight, $auditLogService): void {
            $flight->delete();
            $this->recordAdministratorChange(
                $auditLogService,
                $request->user(),
                $flight,
                'flight_deactivated',
                ['flight_number' => $flight->flight_number],
            );
        });

        return redirect()->route('admin.flights.index')->with('status', 'Flight deactivated.');
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function recordAdministratorChange(
        AuditLogService $auditLogService,
        User $admin,
        Flight $flight,
        string $action,
        array $details,
    ): void {
        $auditLogService->record(
            AuditLog::EVENT_ADMINISTRATOR_CHANGE,
            $admin,
            $flight,
            'Administrator '.$action.'.',
            ['action' => $action, ...$details],
        );
    }

    /**
     * @param  array<string, mixed>  $previous
     * @param  array<string, mixed>  $current
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function flightChanges(array $previous, array $current): array
    {
        $changes = [];

        foreach ([
            'flight_number',
            'airline',
            'departure_airport',
            'arrival_airport',
            'departure_date',
            'departure_time',
            'arrival_time',
            'aircraft',
            'total_seats',
            'available_seats',
            'status',
        ] as $field) {
            if (($previous[$field] ?? null) !== ($current[$field] ?? null)) {
                $changes[$field] = [
                    'from' => $previous[$field] ?? null,
                    'to' => $current[$field] ?? null,
                ];
            }
        }

        return $changes;
    }
}
