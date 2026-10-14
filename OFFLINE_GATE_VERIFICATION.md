# Offline Gate Verification

## Architecture

The central Laravel application remains the authority for bookings, flights, check-ins, boarding passes, staff identities, and audit records. Each gate runs a local Laravel instance with a persistent SQLite database dedicated to verification snapshots and the synchronization outbox. Staff use the local gate application's scanner, so loss of internet access to the central server does not stop local scans.

The local snapshot contains only the fields needed to verify and identify a pass: its reference and ID, the passenger's display name, a SHA-256 hash of the QR verification token, booking/pass/flight status, flight number and destination, seat, departure and expiration times, and its lease and signature. The display name is cached to preserve the existing staff verification view; email addresses and other passenger details are not cached. The raw QR token is never stored. Each scan, including invalid and not-found attempts, is written to SQLite before a result is returned.

Snapshots and offline events are HMAC-signed with the gate terminal's API token. The QR format continues to carry only the pass reference and random verification token; the local terminal hashes that token to compare it with the cached hash. The token and passenger data are not copied into the outbox.

## Reservations and consistency

Before a pass is cached, the central server creates a unique lease for that pass and terminal. Another terminal cannot receive that pass, and central online boarding approval rejects a pass with an active offline lease. A terminal can approve the pass locally once; it is then marked locally used and synchronized as USED centrally.

The default offline authorization window is four hours and is capped at 24 hours. The server can revoke signed leases when it learns that a pass is no longer eligible; the next successful synchronization disables those local snapshots. While a terminal is disconnected, it cannot learn about a booking or flight cancellation made centrally after its last snapshot. Such a pass can therefore remain usable only until its cached authorization expires. Immediate revocation during a network outage is not technically possible without disabling offline operation.

Leases do not expire or transfer automatically, even after the offline authorization window ends. This prevents a second gate from accepting the same pass while the first gate may still be disconnected. Staff must use **Close out terminal** after synchronization to release unused leases. If a terminal is lost, its leases remain reserved; manually releasing them safely requires an audited recovery process that is not currently provided.

## Synchronization and audit

The terminal downloads signed snapshot pages and signed lease revocations from `GET /api/gate/v1/snapshot`. It sends queued records to `POST /api/gate/v1/synchronize`; closeout uses `POST /api/gate/v1/closeout`. Each event has a UUID idempotency key, stored centrally as the unique `sync_id`, so delivery retries do not create duplicate verification records. Invalid signatures, unknown staff mappings, missing reservations, expired authorization, or incompatible central pass state produce a conflict rather than a boarding approval.

Outbox states are `PENDING`, `SYNCING`, `SYNCED`, `FAILED`, and `CONFLICT`. Failed requests retry with exponential delay capped at 60 minutes. Valid scans are held until approved or closeout so a central immutable verification record does not incorrectly imply boarding approval. Other scan results are synchronized as failed-attempt records. Central verification and boarding approval records are accompanied by audit entries.

The staff dashboard shows connection/synchronization state and queue counts. `gate:sync` can be run manually and is also scheduled every minute when terminal mode is enabled.

## Deployment

1. Run the normal central migrations against the central database.
2. Create a terminal credential on the central application with `php artisan gate:terminal:create "Gate A" --gate=A`. Copy the one-time token directly into the terminal's secret store; do not place it in source control or logs.
3. On the gate instance, set `GATE_TERMINAL_MODE=true`, `GATE_TERMINAL_ID`, `GATE_TERMINAL_TOKEN`, and `GATE_CENTRAL_URL`. Keep the token protected and use HTTPS for central communication.
4. Create the persistent SQLite file before migrating on the gate instance, for example:

   ```sh
   install -m 0600 /dev/null database/gate-terminal.sqlite
   php artisan migrate --force
   ```

   The local snapshot, outbox, and sync-state migrations use the `gate_terminal` connection. Set `GATE_SQLITE_PATH` to an absolute persistent path if the default database path is not suitable. Back up and protect this file as operational data.
5. Provision local staff accounts and map each one to its central staff user through `users.central_user_id`. A missing or invalid mapping prevents synchronization; staff must not share a central identity.
6. Run `php artisan gate:sync` and confirm the dashboard reports a successful sync before relying on the terminal. Repeat synchronization after connectivity returns; close out only after queued events have synchronized.

## Verification coverage and limits

Feature tests exercise an online scan, local valid/invalid/already-used scans without central writes, event synchronization and duplicate delivery, exclusive reservations, failed-sync retry, multi-page downloads, cancellation revocation, and rejection after departure. Network restoration in tests is represented by switching the HTTP fake from connection failure to a successful central response; an actual airport network outage and physical camera device still require deployment testing.
