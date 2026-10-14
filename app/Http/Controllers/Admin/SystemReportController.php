<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Flight;
use App\Models\User;
use App\Models\VerificationRecord;
use App\Services\SystemReportService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemReportController extends Controller
{
    public function index(Request $request, SystemReportService $reportService): View
    {
        $filters = $this->validatedFilters($request);
        $report = $filters['report'];
        $query = $reportService->query($report, $filters);
        $total = (clone $query)->count();
        $breakdown = (clone $query)
            ->reorder()
            ->select('outcome', DB::raw('COUNT(*) as total'))
            ->groupBy('outcome')
            ->orderBy('outcome')
            ->get();
        $records = $query->paginate(25)->withQueryString();

        return view('admin.reports.index', [
            'reports' => SystemReportService::REPORTS,
            'selectedReport' => $report,
            'reportTitle' => SystemReportService::REPORTS[$report],
            'filters' => $filters,
            'records' => $records,
            'summary' => [
                'total' => $total,
                'breakdown' => $breakdown,
                'local_sync' => $report === 'synchronization'
                    ? $reportService->localSynchronizationSummary($filters)
                    : [],
            ],
            'flights' => Flight::query()
                ->withTrashed()
                ->orderByDesc('departure_date')
                ->get(['id', 'flight_number', 'departure_date']),
            'airports' => $reportService->airports(),
            'staffMembers' => User::query()
                ->where('role', 'staff')
                ->orderBy('full_name')
                ->get(['id', 'full_name']),
            'results' => VerificationRecord::RESULTS,
            'methods' => VerificationRecord::METHODS,
        ]);
    }

    public function export(Request $request, SystemReportService $reportService): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $report = $filters['report'];
        $query = $reportService->query($report, $filters);
        $filename = sprintf('airport-%s-%s.csv', $report, now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($query, $reportService): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                throw new \RuntimeException('The report export stream could not be opened.');
            }

            fputcsv($output, [
                'Date and time',
                'Reference',
                'Passenger',
                'Flight',
                'Departure airport',
                'Arrival airport',
                'Staff member',
                'Result or status',
                'Method',
                'Synchronization status',
                'Details',
            ]);

            foreach ($query->cursor() as $record) {
                fputcsv($output, [
                    $reportService::csvValue($record->report_date),
                    $reportService::csvValue($record->reference),
                    $reportService::csvValue($record->passenger_name),
                    $reportService::csvValue($record->flight_number),
                    $reportService::csvValue($record->departure_airport),
                    $reportService::csvValue($record->arrival_airport),
                    $reportService::csvValue($record->staff_name),
                    $reportService::csvValue($record->outcome),
                    $reportService::csvValue($record->method),
                    $reportService::csvValue($record->sync_status),
                    $reportService::csvValue($record->details),
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        $airports = array_keys(app(SystemReportService::class)->airports());

        $filters = $request->validate([
            'report' => ['nullable', Rule::in(array_keys(SystemReportService::REPORTS))],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'flight_id' => ['nullable', 'integer', Rule::exists('flights', 'id')],
            'airport' => ['nullable', Rule::in($airports)],
            'verification_result' => ['nullable', Rule::in(VerificationRecord::RESULTS)],
            'staff_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn (Builder $query) => $query->where('role', 'staff')),
            ],
            'verification_method' => ['nullable', Rule::in(VerificationRecord::METHODS)],
        ]);

        return [
            'report' => $filters['report'] ?? 'boarding-activity',
            ...$filters,
        ];
    }
}
