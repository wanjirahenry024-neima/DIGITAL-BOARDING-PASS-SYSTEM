<?php

namespace App\Http\Controllers;

use App\Models\BoardingPass;
use App\Services\QrCodeGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BoardingPassController extends Controller
{
    public function show(
        Request $request,
        BoardingPass $boardingPass,
        QrCodeGenerationService $qrCodeGenerationService,
    ): View {
        $boardingPass = $request->user()->boardingPasses()
            ->with(['booking', 'checkIn', 'flight', 'passenger'])
            ->findOrFail($boardingPass->getKey());
        $qrCode = $qrCodeGenerationService->generate($boardingPass);

        return view('boarding-passes.show', compact('boardingPass', 'qrCode'));
    }

    public function download(
        Request $request,
        BoardingPass $boardingPass,
        QrCodeGenerationService $qrCodeGenerationService,
    ): StreamedResponse {
        $boardingPass = $request->user()->boardingPasses()
            ->with(['booking', 'checkIn', 'flight', 'passenger'])
            ->findOrFail($boardingPass->getKey());
        $qrCode = $qrCodeGenerationService->generate($boardingPass);

        return response()->streamDownload(
            static function () use ($boardingPass, $qrCode): void {
                echo view('boarding-passes.download', compact('boardingPass', 'qrCode'))->render();
            },
            Str::slug('boarding-pass-'.$boardingPass->pass_reference).'.html',
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
