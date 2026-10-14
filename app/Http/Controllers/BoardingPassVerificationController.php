<?php

namespace App\Http\Controllers;

use App\Http\Requests\VerifyBoardingPassRequest;
use App\Models\BoardingPassVerification;
use App\Services\GateOfflineVerificationService;
use App\Services\VerifyBoardingPassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardingPassVerificationController extends Controller
{
    public function scan(
        VerifyBoardingPassRequest $request,
        VerifyBoardingPassService $verifyBoardingPassService,
        GateOfflineVerificationService $gateOfflineVerificationService,
    ): JsonResponse {
        if (
            config('services.gate_terminal.enabled')
            && ! $request->user()->central_user_id
        ) {
            return response()->json([
                'message' => 'This staff account is not mapped to a central user ID.',
            ], 503);
        }

        $result = config('services.gate_terminal.enabled')
            ? $gateOfflineVerificationService->scan(
                $request->string('payload')->toString(),
                $request->user(),
                $request->safe()->only(['gate', 'device_identifier']),
            )
            : $verifyBoardingPassService->scan(
                $request->string('payload')->toString(),
                $request->user(),
                $request->safe()->only(['gate', 'device_identifier']),
            );

        return response()->json($result);
    }

    public function approve(
        Request $request,
        string $verification,
        VerifyBoardingPassService $verifyBoardingPassService,
        GateOfflineVerificationService $gateOfflineVerificationService,
    ): JsonResponse {
        $result = config('services.gate_terminal.enabled')
            ? $gateOfflineVerificationService->approve($verification, $request->user())
            : $verifyBoardingPassService->approve(
                BoardingPassVerification::query()->findOrFail($verification),
                $request->user(),
            );

        $httpStatus = match (true) {
            $result['approved'] => 200,
            in_array($result['status'], [BoardingPassVerification::STATUS_USED, BoardingPassVerification::STATUS_CONFLICT], true) => 409,
            default => 422,
        };

        return response()->json($result, $httpStatus);
    }
}
