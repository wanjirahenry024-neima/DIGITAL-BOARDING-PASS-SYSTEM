# Digital Boarding Pass System Test Process

## Test suites

The project uses PHPUnit with Laravel's SQLite in-memory test database. Tests are divided into unit-level model/service tests, HTTP feature tests, security-tagged tests, and offline-terminal tests.

Run the full regression suite before merging:

```sh
php artisan test --compact
```

Run focused suites while developing:

```sh
vendor/bin/phpunit --testsuite Unit --testdox
vendor/bin/phpunit --testsuite Feature --testdox
vendor/bin/phpunit --group security --testdox
vendor/bin/phpunit --group offline --testdox
```

The full suite is authoritative. A test may belong to both the Feature suite and a named group.

## Coverage map

| Area | Primary tests | Expected behavior |
|---|---|---|
| Models and authentication | `tests/Unit/Models/UserTest.php`, `tests/Feature/FoundationTest.php` | Passwords are hashed and hidden; registration cannot elevate a role; login, logout and recovery work; role boundaries are enforced. |
| Booking logic | `tests/Unit/Services/BookingServiceTest.php`, `tests/Feature/BookingManagementTest.php` | Confirmed bookings reserve seats, cancellation restores them once, and unavailable capacity/status is rejected. |
| Check-in logic | `tests/Unit/Services/CheckInServiceTest.php`, `tests/Feature/CheckInManagementTest.php` | Eligible passengers can check in once, receive a unique seat and cannot bypass booking, flight or deadline constraints. |
| Boarding pass and QR | `tests/Feature/BoardingPassManagementTest.php`, `tests/Feature/BoardingPassVerificationTest.php`, `tests/Unit/Services/VerifyBoardingPassServiceTest.php` | Check-in issues one pass; QR tokens are protected; invalid, expired, cancelled, used and malformed passes are not approved; replay is rejected. |
| Flight and booking features | `tests/Feature/FlightManagementTest.php`, `tests/Feature/BookingManagementTest.php` | Search, filters, booking, cancellation and validation behave as expected. |
| Administration and audit | `tests/Feature/AdminDashboardTest.php`, `tests/Feature/AuditLoggingTest.php`, `tests/Feature/SystemReportTest.php` | Admin-only controls, auditing, safe search, reports and exports work without exposing authentication data. |
| Notifications | `tests/Feature/PassengerNotificationTest.php` | Passenger notifications are created for applicable travel events and notification history is owner-scoped. |
| Security regressions | `tests/Feature/SecurityRegressionTest.php` and tests tagged `security` | SQL-looking input remains data, rendered names are escaped, state-changing forms render CSRF tokens and browser write routes use the `web` middleware. |
| Offline verification | `tests/Feature/OfflineGateVerificationTest.php` and tests tagged `offline` | Local scans, queueing, idempotent sync, retries, deactivation, reservations and conflict handling are covered. |
| Signed synchronization data | `tests/Unit/Services/GateSyncSignatureTest.php` | Event tampering and wrong signing secrets fail verification. |

## Test discipline

- Use factories and `RefreshDatabase` for isolated persistent state; use real SQLite queries rather than mocking database behavior.
- Test requests at the feature boundary for authorization, validation, state changes, and rendered output. Keep tests of pure transformations at the unit level.
- Freeze time for date/deadline cases and fake notifications or outbound HTTP when delivery is not the subject of the test.
- For write paths, assert the response and relevant persisted records; for denied/replay paths, assert that protected state did not change.
- Keep security tests in the `security` group and local-terminal behavior in the `offline` group so those suites can run independently.

## Limits of local tests

Laravel's test environment deliberately bypasses CSRF rejection. The security regression suite therefore verifies CSRF token rendering and that browser write routes are attached to `web` middleware; production CSRF validation remains Laravel middleware behavior. Feature tests use SQLite and exercise duplicate approvals sequentially. They verify transactional outcomes and duplicate rejection, but do not simulate independent MySQL workers racing at the same instant. Run database-engine-specific concurrency/load tests against a staging MySQL instance before production changes to locking behavior.
