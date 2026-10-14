<?php

namespace App\Services;

use App\Models\BoardingPass;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\DB;

class QrCodeGenerationService
{
    public function generate(BoardingPass $boardingPass): string
    {
        $boardingPass = $this->ensureVerificationToken($boardingPass);
        $payload = implode('|', [
            BoardingPass::QR_FORMAT_VERSION,
            $boardingPass->pass_reference,
            $boardingPass->verification_token,
        ]);

        return (new Builder(
            writer: new SvgWriter,
            writerOptions: [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true],
            validateResult: false,
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
        ))->build()->getDataUri();
    }

    private function ensureVerificationToken(BoardingPass $boardingPass): BoardingPass
    {
        if ($boardingPass->verification_token !== null && $boardingPass->verification_token_hash !== null) {
            return $boardingPass;
        }

        return DB::transaction(function () use ($boardingPass): BoardingPass {
            $lockedPass = BoardingPass::query()
                ->whereKey($boardingPass->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPass->verification_token === null || $lockedPass->verification_token_hash === null) {
                $token = bin2hex(random_bytes(32));
                $lockedPass->forceFill([
                    'verification_token' => $token,
                    'verification_token_hash' => hash('sha256', $token),
                ])->save();
            }

            return $lockedPass;
        }, attempts: 3);
    }
}
