@extends('layouts.app')

@section('title', 'Gate staff | ' . config('app.name'))

@section('content')
    <section class="container-fluid page-wrap staff-dashboard py-5">
        <p class="eyebrow mb-2">Airport staff</p>
        <h1 class="h2 mb-2">Boarding pass verification</h1>
        <p class="text-muted-custom mb-4">Signed in as {{ auth()->user()->full_name }}.
            @if ($gateTerminalMode)
                Scans are checked against this terminal's local SQLite cache.
            @else
                Scans are verified by the central server.
            @endif
        </p>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="content-panel staff-stat p-3 h-100"><p class="mono-label mb-1">Verifications today</p><p class="h3 mb-0">{{ $statistics['today'] }}</p></div></div>
            <div class="col-6 col-lg-3"><div class="content-panel staff-stat p-3 h-100"><p class="mono-label mb-1">Boarding approved</p><p class="h3 mb-0">{{ $statistics['approved'] }}</p></div></div>
            <div class="col-6 col-lg-3"><div class="content-panel staff-stat p-3 h-100"><p class="mono-label mb-1">Awaiting approval</p><p class="h3 mb-0">{{ $statistics['valid_pending'] }}</p></div></div>
            <div class="col-6 col-lg-3"><div class="content-panel staff-stat p-3 h-100"><p class="mono-label mb-1">Failed verifications</p><p class="h3 mb-0">{{ $statistics['failed'] }}</p></div></div>
        </div>

        <div class="row g-4">
            <div class="col-xl-7 scanner-column">
                <section
                    class="content-panel scanner-card p-4 p-lg-4"
                    id="boarding-pass-scanner"
                    data-scan-url="{{ route('staff.boarding-passes.verify') }}"
                    data-approve-url-template="{{ route('staff.boarding-pass-verifications.approve', ['verification' => '__VERIFICATION_ID__']) }}"
                    data-status-url="{{ route('staff.gate.status') }}"
                    data-sync-url="{{ route('staff.gate.synchronize') }}"
                    data-closeout-url="{{ route('staff.gate.closeout') }}"
                    data-gate-terminal-mode="{{ $gateTerminalMode ? 'true' : 'false' }}"
                >
                    <h2 class="h4 mb-3">Scan boarding pass</h2>
                    <div id="gate-sync-status" class="alert {{ $gateTerminalMode ? 'alert-info' : 'alert-secondary' }} py-2" role="status" aria-live="polite">
                        @if ($gateTerminalMode)
                            Central link {{ $gateStatus['connection_status'] }}; local SQLite has {{ $gateStatus['cached_passes'] }} cached passes.
                        @else
                            Central server verification is active. Offline mode: Not available on this server.
                        @endif
                    </div>
                    @if ($gateTerminalMode)
                        <p id="gate-sync-queue" class="small text-muted-custom">
                            Pending {{ $gateStatus['pending'] }} · Failed {{ $gateStatus['failed'] }} · Conflicts {{ $gateStatus['conflict'] }}
                            @if ($gateStatus['last_synced_at'])
                                · Last sync {{ $gateStatus['last_synced_at'] }}
                            @endif
                        </p>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button class="btn btn-outline-primary" id="sync-gate-terminal" type="button">Synchronize now</button>
                            <button class="btn btn-outline-danger" id="close-gate-terminal" type="button">Close offline session</button>
                        </div>
                    @endif
                    <p id="scanner-support-message" class="small text-muted-custom">Use a camera on a secure connection or enter a decoded QR payload.</p>
                    <video id="boarding-pass-camera" class="w-100 rounded bg-dark mb-3" playsinline muted hidden aria-label="Camera preview"></video>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button class="btn btn-outline-primary" id="start-camera" type="button">Start camera</button>
                        <button class="btn btn-outline-secondary" id="stop-camera" type="button" disabled>Stop camera</button>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="scan-gate">Gate (optional)</label>
                            <input class="form-control" id="scan-gate" maxlength="50">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="scan-device-identifier">Device identifier (optional)</label>
                            <input class="form-control" id="scan-device-identifier" maxlength="100">
                        </div>
                    </div>
                    <form id="manual-scan-form">
                        <label class="form-label" for="qr-payload">QR verification payload</label>
                        <textarea class="form-control font-monospace" id="qr-payload" rows="3" maxlength="110" required placeholder="DBP1|BP-...|..."></textarea>
                        <button class="btn btn-primary mt-3" type="submit">Verify boarding pass</button>
                    </form>
                    <div id="scanner-feedback" class="mt-3" role="status" aria-live="polite"></div>
                    <section id="scan-result" class="border rounded p-3 mt-3" aria-live="polite" hidden>
                        <h3 class="h5">Verification result: <span id="result-status"></span></h3>
                        <p id="result-reason" class="mb-2"></p>
                        <dl class="row mb-3">
                            <dt class="col-5">Passenger</dt><dd class="col-7" id="result-passenger"></dd>
                            <dt class="col-5">Flight</dt><dd class="col-7" id="result-flight"></dd>
                            <dt class="col-5">Destination</dt><dd class="col-7" id="result-destination"></dd>
                            <dt class="col-5">Seat</dt><dd class="col-7" id="result-seat"></dd>
                            <dt class="col-5">Boarding Pass ID</dt><dd class="col-7" id="result-pass-id"></dd>
                            <dt class="col-5">Verification Time</dt><dd class="col-7" id="result-time"></dd>
                        </dl>
                        <button class="btn btn-success" id="approve-boarding" type="button" hidden>Approve boarding</button>
                    </section>
                </section>
            </div>

            <div class="col-xl-5">
                <section class="content-panel recent-verifications p-4">
                    <h2 class="h4 mb-3">Recent verifications</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Passenger</th><th>Pass / Flight</th><th>Destination</th><th>Status</th><th>Verified</th></tr></thead>
                            <tbody>
                                @forelse ($recentVerifications as $verification)
                                    <tr>
                                        <td>{{ $verification->boardingPass?->passenger?->full_name ?? '—' }}</td>
                                        <td>
                                            {{ $verification->pass_reference ?? '—' }}
                                            <span class="d-block small text-muted-custom">{{ $verification->boardingPass?->flight?->flight_number ?? '—' }}</span>
                                        </td>
                                        <td>{{ $verification->boardingPass?->flight?->arrival_airport ?? '—' }}</td>
                                        <td>
                                            @php
                                                $statusColor = match ($verification->status) {
                                                    'VALID' => 'success',
                                                    'USED', 'EXPIRED' => 'warning',
                                                    'CANCELLED', 'INVALID', 'NOT_FOUND' => 'danger',
                                                    default => 'secondary',
                                                };
                                            @endphp
                                            <span class="badge text-bg-{{ $statusColor }}">{{ $verification->status }}</span>
                                        </td>
                                        <td>{{ $verification->verified_at->format('M j, H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted-custom py-4">No verification attempts yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </section>

    <script>
        (() => {
            const scanner = document.querySelector('#boarding-pass-scanner');
            const video = document.querySelector('#boarding-pass-camera');
            const feedback = document.querySelector('#scanner-feedback');
            const resultPanel = document.querySelector('#scan-result');
            const approveButton = document.querySelector('#approve-boarding');
            const supportMessage = document.querySelector('#scanner-support-message');
            const gateTerminalMode = scanner.dataset.gateTerminalMode === 'true';
            let cameraStream = null;
            let barcodeDetector = null;
            let scanning = false;
            let verificationId = null;

            const stopCamera = () => {
                scanning = false;
                cameraStream?.getTracks().forEach((track) => track.stop());
                cameraStream = null;
                video.srcObject = null;
                video.hidden = true;
                document.querySelector('#start-camera').disabled = false;
                document.querySelector('#stop-camera').disabled = true;
            };

            const displayResult = (data) => {
                resultPanel.hidden = false;
                const status = data.status ?? 'INVALID';
                resultPanel.classList.toggle('result-valid', status === 'VALID');
                resultPanel.classList.toggle('result-invalid', status !== 'VALID');
                document.querySelector('#result-status').textContent = status;
                document.querySelector('#result-reason').textContent = data.reason ?? '';
                document.querySelector('#result-passenger').textContent = data.passenger ?? '—';
                document.querySelector('#result-flight').textContent = data.flight ?? '—';
                document.querySelector('#result-destination').textContent = data.destination ?? '—';
                document.querySelector('#result-seat').textContent = data.seat ?? '—';
                document.querySelector('#result-pass-id').textContent = data.boarding_pass_id ?? '—';
                document.querySelector('#result-time').textContent = data.verified_at
                    ? new Date(data.verified_at).toLocaleString()
                    : '—';
                verificationId = data.verification_id ?? null;
                approveButton.hidden = !data.can_approve;
                feedback.textContent = '';
            };

            const postJson = async (url, body = {}) => {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(body),
                });
                const data = await response.json();

                if (!response.ok && !data.status) {
                    throw new Error(data.message ?? 'The request could not be completed.');
                }

                return data;
            };

            const refreshGateStatus = async () => {
                if (!gateTerminalMode) {
                    return;
                }

                try {
                    const response = await fetch(scanner.dataset.statusUrl, {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' },
                    });
                    if (!response.ok) {
                        throw new Error('Could not load gate synchronization status.');
                    }

                    const status = await response.json();
                    document.querySelector('#gate-sync-status').textContent =
                        `Central link ${status.connection_status}; local SQLite has ${status.cached_passes} cached passes. Sync: ${status.sync_status}.`;
                    document.querySelector('#gate-sync-queue').textContent =
                        `Pending ${status.pending} · Failed ${status.failed} · Conflicts ${status.conflict}`
                        + (status.last_synced_at ? ` · Last sync ${status.last_synced_at}` : '')
                        + (status.last_error ? ` · ${status.last_error}` : '');
                } catch (error) {
                    document.querySelector('#gate-sync-status').textContent = `Local status unavailable: ${error.message}`;
                }
            };

            const verifyPayload = async (payload) => {
                stopCamera();
                approveButton.hidden = true;
                resultPanel.hidden = true;
                resultPanel.classList.remove('result-valid', 'result-invalid');
                feedback.textContent = 'Verifying boarding pass…';

                try {
                    const data = await postJson(scanner.dataset.scanUrl, {
                        payload,
                        gate: document.querySelector('#scan-gate').value.trim() || null,
                        device_identifier: document.querySelector('#scan-device-identifier').value.trim() || null,
                    });
                    displayResult(data);
                } catch (error) {
                    feedback.textContent = `Verification failed: ${error.message}`;
                }
            };

            const scanFrame = async () => {
                if (!scanning || video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA) {
                    if (scanning) {
                        window.setTimeout(scanFrame, 300);
                    }
                    return;
                }

                try {
                    const barcodes = await barcodeDetector.detect(video);
                    if (barcodes.length > 0) {
                        await verifyPayload(barcodes[0].rawValue);
                        return;
                    }
                } catch {
                    stopCamera();
                    feedback.textContent = 'The camera could not read this QR code. Enter its payload manually or retry.';
                    return;
                }

                if (scanning) {
                    window.setTimeout(scanFrame, 300);
                }
            };

            document.querySelector('#start-camera').addEventListener('click', async () => {
                if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                    supportMessage.textContent = 'Camera scanning requires a supported mobile browser and a secure HTTPS connection. Enter the decoded payload instead.';
                    return;
                }

                if (!('BarcodeDetector' in window)) {
                    supportMessage.textContent = 'This browser does not support QR camera detection. Enter the decoded payload instead.';
                    return;
                }

                try {
                    barcodeDetector = new BarcodeDetector({ formats: ['qr_code'] });
                    cameraStream = await navigator.mediaDevices.getUserMedia({
                        audio: false,
                        video: { facingMode: { ideal: 'environment' } },
                    });
                    video.srcObject = cameraStream;
                    video.hidden = false;
                    await video.play();
                    scanning = true;
                    document.querySelector('#start-camera').disabled = true;
                    document.querySelector('#stop-camera').disabled = false;
                    supportMessage.textContent = 'Point the camera at a boarding pass QR code.';
                    scanFrame();
                } catch {
                    stopCamera();
                    feedback.textContent = 'Camera access was unavailable. Check browser permissions or enter the decoded payload.';
                }
            });

            document.querySelector('#stop-camera').addEventListener('click', stopCamera);
            document.querySelector('#manual-scan-form').addEventListener('submit', (event) => {
                event.preventDefault();
                verifyPayload(document.querySelector('#qr-payload').value.trim());
            });

            approveButton.addEventListener('click', async () => {
                if (verificationId === null) {
                    return;
                }

                approveButton.disabled = true;
                feedback.textContent = 'Approving boarding…';

                try {
                    const url = scanner.dataset.approveUrlTemplate.replace('__VERIFICATION_ID__', verificationId);
                    const data = await postJson(url);
                    displayResult(data);

                    if (data.approved) {
                        window.location.reload();
                    }
                } catch (error) {
                    feedback.textContent = `Boarding approval failed: ${error.message}`;
                } finally {
                    approveButton.disabled = false;
                }
            });

            if (gateTerminalMode) {
                document.querySelector('#sync-gate-terminal').addEventListener('click', async () => {
                    const button = document.querySelector('#sync-gate-terminal');
                    button.disabled = true;
                    feedback.textContent = 'Synchronizing terminal cache and verification queue…';
                    try {
                        const status = await postJson(scanner.dataset.syncUrl);
                        feedback.textContent = status.connected
                            ? 'Terminal synchronization completed.'
                            : (status.last_error ?? status.message ?? 'Central server is unavailable.');
                    } catch (error) {
                        feedback.textContent = `Synchronization failed: ${error.message}`;
                    } finally {
                        button.disabled = false;
                        await refreshGateStatus();
                    }
                });

                document.querySelector('#close-gate-terminal').addEventListener('click', async () => {
                    if (!window.confirm('Stop local verification, synchronize pending records, and release unused pass reservations?')) {
                        return;
                    }

                    const button = document.querySelector('#close-gate-terminal');
                    button.disabled = true;
                    feedback.textContent = 'Disabling the local cache before releasing reservations…';
                    try {
                        const result = await postJson(scanner.dataset.closeoutUrl);
                        feedback.textContent = result.closed
                            ? `Offline session closed; ${result.released} unused reservations released.`
                            : result.message;
                    } catch (error) {
                        feedback.textContent = `Closeout failed: ${error.message} Local scanning remains disabled.`;
                    } finally {
                        button.disabled = false;
                        await refreshGateStatus();
                    }
                });

                refreshGateStatus();
                window.setInterval(refreshGateStatus, 30000);
            }

            window.addEventListener('pagehide', stopCamera);
        })();
    </script>
@endsection
