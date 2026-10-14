<dl class="pass-details">
    <div><dt>Passenger</dt><dd>{{ $boardingPass->passenger->full_name }}</dd></div>
    <div><dt>Flight</dt><dd>{{ $boardingPass->flight->flight_number }}</dd></div>
    <div><dt>From</dt><dd>{{ $boardingPass->flight->departure_airport }}</dd></div>
    <div><dt>To</dt><dd>{{ $boardingPass->flight->arrival_airport }}</dd></div>
    <div><dt>Date</dt><dd>{{ $boardingPass->flight->departure_date->format('M j, Y') }}</dd></div>
    <div><dt>Time</dt><dd>{{ substr($boardingPass->flight->departure_time, 0, 5) }}</dd></div>
    <div><dt>Seat</dt><dd>{{ $boardingPass->seat_number }}</dd></div>
    <div><dt>Boarding pass reference</dt><dd>{{ $boardingPass->pass_reference }}</dd></div>
    <div><dt>Status</dt><dd>{{ $boardingPass->pass_status }}</dd></div>
</dl>
<img class="boarding-pass-qr" src="{{ $qrCode }}" alt="Secure boarding pass verification QR code" width="200" height="200">
