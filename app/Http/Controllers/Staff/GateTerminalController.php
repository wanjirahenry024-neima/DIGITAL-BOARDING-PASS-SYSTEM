<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\GateSyncService;
use Illuminate\Http\JsonResponse;

class GateTerminalController extends Controller
{
    public function status(GateSyncService $gateSyncService): JsonResponse
    {
        return response()->json($gateSyncService->status());
    }

    public function synchronize(GateSyncService $gateSyncService): JsonResponse
    {
        $result = $gateSyncService->synchronize();

        return response()->json($result, $result['connected'] ? 200 : 503);
    }

    public function closeout(GateSyncService $gateSyncService): JsonResponse
    {
        $result = $gateSyncService->closeout();

        return response()->json($result, $result['closed'] ? 200 : 409);
    }
}
