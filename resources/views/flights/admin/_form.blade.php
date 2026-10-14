<form method="POST" action="{{ $action }}">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <p class="fw-semibold mb-2">Review the highlighted flight details.</p>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="flight_number">Flight number</label>
            <input class="form-control @error('flight_number') is-invalid @enderror" id="flight_number" name="flight_number" value="{{ old('flight_number', $flight->flight_number ?? '') }}" maxlength="20" required>
            @error('flight_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="airline">Airline</label>
            <input class="form-control @error('airline') is-invalid @enderror" id="airline" name="airline" value="{{ old('airline', $flight->airline ?? '') }}" maxlength="100" required>
            @error('airline') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="aircraft">Aircraft</label>
            <input class="form-control @error('aircraft') is-invalid @enderror" id="aircraft" name="aircraft" value="{{ old('aircraft', $flight->aircraft ?? '') }}" maxlength="100">
            @error('aircraft') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="departure_airport">Departure airport</label>
            <input class="form-control @error('departure_airport') is-invalid @enderror" id="departure_airport" name="departure_airport" value="{{ old('departure_airport', $flight->departure_airport ?? '') }}" maxlength="100" required>
            @error('departure_airport') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="arrival_airport">Arrival airport</label>
            <input class="form-control @error('arrival_airport') is-invalid @enderror" id="arrival_airport" name="arrival_airport" value="{{ old('arrival_airport', $flight->arrival_airport ?? '') }}" maxlength="100" required>
            @error('arrival_airport') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="departure_date">Departure date</label>
            <input class="form-control @error('departure_date') is-invalid @enderror" id="departure_date" name="departure_date" type="date" min="{{ now()->toDateString() }}" value="{{ old('departure_date', isset($flight) ? $flight->departure_date->format('Y-m-d') : '') }}" required>
            @error('departure_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="departure_time">Departure time</label>
            <input class="form-control @error('departure_time') is-invalid @enderror" id="departure_time" name="departure_time" type="time" value="{{ old('departure_time', isset($flight) ? substr($flight->departure_time, 0, 5) : '') }}" required>
            @error('departure_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="arrival_time">Arrival time</label>
            <input class="form-control @error('arrival_time') is-invalid @enderror" id="arrival_time" name="arrival_time" type="time" value="{{ old('arrival_time', isset($flight) ? substr($flight->arrival_time, 0, 5) : '') }}" required>
            @error('arrival_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="total_seats">Total seats</label>
            <input class="form-control @error('total_seats') is-invalid @enderror" id="total_seats" name="total_seats" type="number" min="1" max="10000" value="{{ old('total_seats', $flight->total_seats ?? '') }}" required>
            @error('total_seats') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="available_seats">Available seats</label>
            <input class="form-control @error('available_seats') is-invalid @enderror" id="available_seats" name="available_seats" type="number" min="0" max="10000" value="{{ old('available_seats', $flight->available_seats ?? '') }}" required>
            @error('available_seats') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="status">Status</label>
            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $flight->status ?? \App\Models\Flight::STATUS_SCHEDULED) === $status)>{{ $status }}</option>
                @endforeach
            </select>
            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
        <a class="btn btn-outline-secondary" href="{{ route('admin.flights.index') }}">Cancel</a>
    </div>
</form>