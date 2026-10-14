<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BoardingPass;
use App\Models\Booking;
use App\Models\CheckIn;
use App\Models\Flight;
use App\Models\GateVerificationOutbox;
use App\Models\VerificationRecord;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemReportService
{
    /**
     * @var array<string, string>
     */
    public const REPORTS = [
        'boarding-activity' => 'Boarding activity',
        'flights' => 'Flight report',
        'passengers' => 'Passenger report',
        'bookings' => 'Booking report',
        'check-ins' => 'Check-in report',
        'boarding-passes' => 'Boarding pass report',
        'qr-verifications' => 'QR verification report',
        'failed-verifications' => 'Failed verification report',
        'offline-verifications' => 'Offline verification report',
        'synchronization' => 'Synchronization report',
        'audit-activity' => 'Audit activity report',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(string $report, array $filters): Builder
    {
        $query = match ($report) {
            'boarding-activity' => $this->boardingActivityQuery(),
            'flights' => $this->flightQuery(),
            'passengers' => $this->passengerQuery(),
            'bookings' => $this->bookingQuery(),
            'check-ins' => $this->checkInQuery(),
            'boarding-passes' => $this->boardingPassQuery(),
            'qr-verifications' => $this->verificationQuery(),
            'failed-verifications' => $this->verificationQuery()
                ->where('vr.result', '!=', VerificationRecord::RESULT_VALID),
            'offline-verifications' => $this->verificationQuery()
                ->where('vr.verification_method', VerificationRecord::METHOD_OFFLINE),
            'synchronization' => $this->verificationQuery()
                ->where(function (Builder $query): void {
                    $query->whereNotNull('vr.sync_id')
                        ->orWhere('vr.sync_status', '!=', VerificationRecord::SYNC_SYNCED);
                }),
            'audit-activity' => $this->auditQuery(),
            default => throw new \InvalidArgumentException('Unknown operational report.'),
        };

        $this->applyFilters($query, $report, $filters);

        $idColumn = match ($report) {
            'boarding-activity', 'boarding-passes' => 'bp.id',
            'flights' => 'flight.id',
            'passengers' => 'passenger.id',
            'bookings' => 'booking.id',
            'check-ins' => 'check_in.id',
            'audit-activity' => 'audit.id',
            default => 'vr.id',
        };

        return $query->orderByDesc('report_date')->orderByDesc($idColumn);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function localSynchronizationSummary(array $filters): array
    {
        if (
            ! config('services.gate_terminal.enabled')
            || ! Schema::connection('gate_terminal')->hasTable('gate_verification_outbox')
        ) {
            return [];
        }

        $query = GateVerificationOutbox::query();
        if (isset($filters['start_date'])) {
            $query->whereDate('verified_at', '>=', $filters['start_date']);
        }
        if (isset($filters['end_date'])) {
            $query->whereDate('verified_at', '<=', $filters['end_date']);
        }
        if (isset($filters['flight_id'])) {
            $query->where('flight_id', $filters['flight_id']);
        }
        if (isset($filters['airport'])) {
            $flightIds = DB::table('flights')
                ->where(function (Builder $query) use ($filters): void {
                    $query->where('departure_airport', $filters['airport'])
                        ->orWhere('arrival_airport', $filters['airport']);
                })
                ->pluck('id');
            $query->whereIn('flight_id', $flightIds);
        }
        if (isset($filters['verification_result'])) {
            $query->where('result', $filters['verification_result']);
        }
        if (isset($filters['staff_id'])) {
            $query->where('staff_id', $filters['staff_id']);
        }
        if (isset($filters['verification_method'])) {
            $query->where('verification_method', $filters['verification_method']);
        }

        return $query->select('sync_status', DB::raw('COUNT(*) as total'))
            ->groupBy('sync_status')
            ->orderBy('sync_status')
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->sync_status => (int) $row->total])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function airports(): array
    {
        $airports = DB::table('flights')
            ->select('departure_airport as airport')
            ->union(DB::table('flights')->select('arrival_airport as airport'))
            ->orderBy('airport')
            ->pluck('airport')
            ->filter()
            ->unique()
            ->values();

        return $airports->mapWithKeys(fn (string $airport): array => [$airport => $airport])->all();
    }

    private function boardingActivityQuery(): Builder
    {
        return DB::table('boarding_passes as bp')
            ->leftJoin('users as passenger', 'passenger.id', '=', 'bp.passenger_id')
            ->leftJoin('flights as flight', 'flight.id', '=', 'bp.flight_id')
            ->whereNotNull('bp.used_at')
            ->select([
                'bp.id',
                'bp.used_at as report_date',
                'bp.pass_reference as reference',
                'passenger.full_name as passenger_name',
                'flight.flight_number',
                'flight.departure_airport',
                'flight.arrival_airport',
                'bp.pass_status as outcome',
                'bp.seat_number as details',
            ])
            ->selectSub(
                DB::table('audit_logs as approval')
                    ->join('users as approving_staff', 'approving_staff.id', '=', 'approval.actor_id')
                    ->select('approving_staff.full_name')
                    ->where('approval.event', AuditLog::EVENT_BOARDING_APPROVED)
                    ->where('approval.subject_type', BoardingPass::class)
                    ->whereColumn('approval.subject_id', 'bp.id')
                    ->orderByDesc('approval.occurred_at')
                    ->limit(1),
                'staff_name',
            )
            ->selectRaw('NULL as method, NULL as sync_status');
    }

    private function flightQuery(): Builder
    {
        return DB::table('flights as flight')
            ->select([
                'flight.id',
                'flight.departure_date as report_date',
                'flight.flight_number as reference',
                'flight.flight_number',
                'flight.departure_airport',
                'flight.arrival_airport',
                'flight.status as outcome',
            ])
            ->selectRaw('NULL as passenger_name, NULL as staff_name, NULL as method, NULL as sync_status')
            ->selectRaw(
                '(SELECT COUNT(*) FROM bookings WHERE bookings.flight_id = flight.id) as details',
            );
    }

    private function passengerQuery(): Builder
    {
        return DB::table('users as passenger')
            ->where('passenger.role', 'passenger')
            ->select([
                'passenger.id',
                'passenger.created_at as report_date',
                'passenger.id as reference',
                'passenger.full_name as passenger_name',
                'passenger.status as outcome',
            ])
            ->selectRaw('NULL as flight_number, NULL as departure_airport, NULL as arrival_airport, NULL as staff_name, NULL as method, NULL as sync_status')
            ->selectRaw(
                '(SELECT COUNT(*) FROM bookings WHERE bookings.passenger_id = passenger.id) as details',
            );
    }

    private function bookingQuery(): Builder
    {
        return DB::table('bookings as booking')
            ->leftJoin('users as passenger', 'passenger.id', '=', 'booking.passenger_id')
            ->leftJoin('flights as flight', 'flight.id', '=', 'booking.flight_id')
            ->select([
                'booking.id',
                'booking.booking_date as report_date',
                'booking.booking_reference as reference',
                'passenger.full_name as passenger_name',
                'flight.flight_number',
                'flight.departure_airport',
                'flight.arrival_airport',
                'booking.booking_status as outcome',
                'booking.number_of_passengers as details',
            ])
            ->selectRaw('NULL as staff_name, NULL as method, NULL as sync_status');
    }

    private function checkInQuery(): Builder
    {
        return DB::table('check_ins as check_in')
            ->leftJoin('users as passenger', 'passenger.id', '=', 'check_in.passenger_id')
            ->leftJoin('flights as flight', 'flight.id', '=', 'check_in.flight_id')
            ->select([
                'check_in.id',
                'check_in.checked_in_at as report_date',
                'check_in.check_in_reference as reference',
                'passenger.full_name as passenger_name',
                'flight.flight_number',
                'flight.departure_airport',
                'flight.arrival_airport',
                'check_in.status as outcome',
                'check_in.seat_number as details',
            ])
            ->selectRaw('NULL as staff_name, NULL as method, NULL as sync_status');
    }

    private function boardingPassQuery(): Builder
    {
        return DB::table('boarding_passes as bp')
            ->leftJoin('users as passenger', 'passenger.id', '=', 'bp.passenger_id')
            ->leftJoin('flights as flight', 'flight.id', '=', 'bp.flight_id')
            ->select([
                'bp.id',
                'bp.issued_at as report_date',
                'bp.pass_reference as reference',
                'passenger.full_name as passenger_name',
                'flight.flight_number',
                'flight.departure_airport',
                'flight.arrival_airport',
                'bp.pass_status as outcome',
                'bp.seat_number as details',
            ])
            ->selectRaw('NULL as staff_name, NULL as method, NULL as sync_status');
    }

    private function verificationQuery(): Builder
    {
        return DB::table('verification_records as vr')
            ->leftJoin('users as passenger', 'passenger.id', '=', 'vr.passenger_id')
            ->leftJoin('users as staff', 'staff.id', '=', 'vr.staff_id')
            ->leftJoin('flights as flight', 'flight.id', '=', 'vr.flight_id')
            ->select([
                'vr.id',
                'vr.verified_at as report_date',
                'vr.verification_reference as reference',
                'passenger.full_name as passenger_name',
                'flight.flight_number',
                'flight.departure_airport',
                'flight.arrival_airport',
                'vr.result as outcome',
                'staff.full_name as staff_name',
                'vr.verification_method as method',
                'vr.sync_status',
                'vr.gate as details',
            ]);
    }

    private function auditQuery(): Builder
    {
        return DB::table('audit_logs as audit')
            ->leftJoin('users as actor', 'actor.id', '=', 'audit.actor_id')
            ->select([
                'audit.id',
                'audit.occurred_at as report_date',
                'audit.event as reference',
                'actor.full_name as staff_name',
                'audit.event as outcome',
                'audit.description as details',
            ])
            ->selectRaw('NULL as passenger_name, NULL as flight_number, NULL as departure_airport, NULL as arrival_airport, NULL as method, NULL as sync_status');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, string $report, array $filters): void
    {
        $dateColumn = match ($report) {
            'boarding-activity' => 'bp.used_at',
            'flights' => 'flight.departure_date',
            'passengers' => 'passenger.created_at',
            'bookings' => 'booking.booking_date',
            'check-ins' => 'check_in.checked_in_at',
            'boarding-passes' => 'bp.issued_at',
            'audit-activity' => 'audit.occurred_at',
            default => 'vr.verified_at',
        };

        if (isset($filters['start_date'])) {
            $query->whereDate($dateColumn, '>=', $filters['start_date']);
        }
        if (isset($filters['end_date'])) {
            $query->whereDate($dateColumn, '<=', $filters['end_date']);
        }

        if (isset($filters['flight_id'])) {
            if ($report === 'flights') {
                $query->where('flight.id', $filters['flight_id']);
            } elseif ($report === 'passengers') {
                $query->whereExists(function (Builder $subquery) use ($filters): void {
                    $subquery->selectRaw('1')
                        ->from('bookings as report_booking')
                        ->whereColumn('report_booking.passenger_id', 'passenger.id')
                        ->where('report_booking.flight_id', $filters['flight_id']);
                });
            } elseif ($report !== 'audit-activity') {
                $query->where('flight.id', $filters['flight_id']);
            }
        }

        if (isset($filters['airport'])) {
            if ($report === 'flights') {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->where('flight.departure_airport', $filters['airport'])
                        ->orWhere('flight.arrival_airport', $filters['airport']);
                });
            } elseif ($report === 'passengers') {
                $query->whereExists(function (Builder $subquery) use ($filters): void {
                    $subquery->selectRaw('1')
                        ->from('bookings as report_booking')
                        ->join('flights as report_flight', 'report_flight.id', '=', 'report_booking.flight_id')
                        ->whereColumn('report_booking.passenger_id', 'passenger.id')
                        ->where(function (Builder $subquery) use ($filters): void {
                            $subquery->where('report_flight.departure_airport', $filters['airport'])
                                ->orWhere('report_flight.arrival_airport', $filters['airport']);
                        });
                });
            } elseif ($report !== 'audit-activity') {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->where('flight.departure_airport', $filters['airport'])
                        ->orWhere('flight.arrival_airport', $filters['airport']);
                });
            }
        }

        if (isset($filters['verification_result']) && in_array($report, [
            'qr-verifications',
            'failed-verifications',
            'offline-verifications',
            'synchronization',
        ], true)) {
            $query->where('vr.result', $filters['verification_result']);
        }
        if (isset($filters['staff_id'])) {
            if (in_array($report, [
                'qr-verifications',
                'failed-verifications',
                'offline-verifications',
                'synchronization',
            ], true)) {
                $query->where('vr.staff_id', $filters['staff_id']);
            } elseif ($report === 'audit-activity') {
                $query->where('audit.actor_id', $filters['staff_id']);
            } elseif ($report === 'boarding-activity') {
                $query->whereExists(function (Builder $subquery) use ($filters): void {
                    $subquery->selectRaw('1')
                        ->from('audit_logs as report_approval')
                        ->where('report_approval.event', AuditLog::EVENT_BOARDING_APPROVED)
                        ->where('report_approval.subject_type', BoardingPass::class)
                        ->whereColumn('report_approval.subject_id', 'bp.id')
                        ->where('report_approval.actor_id', $filters['staff_id']);
                });
            }
        }
        if (isset($filters['verification_method']) && in_array($report, [
            'qr-verifications',
            'failed-verifications',
            'offline-verifications',
            'synchronization',
        ], true)) {
            $query->where('vr.verification_method', $filters['verification_method']);
        }

        if (
            $report === 'audit-activity'
            && (isset($filters['flight_id']) || isset($filters['airport']))
        ) {
            $this->applyAuditFlightFilters($query, $filters);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyAuditFlightFilters(Builder $query, array $filters): void
    {
        $flightIds = DB::table('flights')->select('id');

        if (isset($filters['flight_id'])) {
            $flightIds->where('id', $filters['flight_id']);
        }
        if (isset($filters['airport'])) {
            $flightIds->where(function (Builder $query) use ($filters): void {
                $query->where('departure_airport', $filters['airport'])
                    ->orWhere('arrival_airport', $filters['airport']);
            });
        }

        $query->where(function (Builder $query) use ($flightIds): void {
            $query->where(function (Builder $query) use ($flightIds): void {
                $query->where('audit.subject_type', Flight::class)
                    ->whereIn('audit.subject_id', clone $flightIds);
            })->orWhere(function (Builder $query) use ($flightIds): void {
                $query->where('audit.subject_type', Booking::class)
                    ->whereIn('audit.subject_id', DB::table('bookings')
                        ->select('id')
                        ->whereIn('flight_id', clone $flightIds));
            })->orWhere(function (Builder $query) use ($flightIds): void {
                $query->where('audit.subject_type', CheckIn::class)
                    ->whereIn('audit.subject_id', DB::table('check_ins')
                        ->select('id')
                        ->whereIn('flight_id', clone $flightIds));
            })->orWhere(function (Builder $query) use ($flightIds): void {
                $query->where('audit.subject_type', BoardingPass::class)
                    ->whereIn('audit.subject_id', DB::table('boarding_passes')
                        ->select('id')
                        ->whereIn('flight_id', clone $flightIds));
            })->orWhere(function (Builder $query) use ($flightIds): void {
                $query->where('audit.subject_type', VerificationRecord::class)
                    ->whereIn('audit.subject_id', DB::table('verification_records')
                        ->select('id')
                        ->whereIn('flight_id', clone $flightIds));
            });
        });
    }

    public static function csvValue(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/\A[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
