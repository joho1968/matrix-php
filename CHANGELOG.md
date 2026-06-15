# Changelog

All notable changes to matrix-php will be documented here.

## [0.90.2] — 2026-06-15

### Added

- `sendStateEvent( $roomId, $eventType, $stateKey, $content )` — generic method
  for sending any Matrix state event via the client API using the admin token.
- `getStateEvent( $roomId, $eventType, $stateKey )` — fetch a single state event
  from a room; returns null if not found (404).

### Changed

- `sendTombstone()` now delegates to `sendStateEvent()` internally rather than
  building the HTTP call directly.

## [0.90.1] — 2026-06-15

### Fixed

- `purgeRemoteMedia()` used HTTP `DELETE` instead of `POST`, causing a 405 from
  Synapse. Corrected to `POST /_synapse/admin/v1/purge_media_cache`.

## [0.90.0] — 2026-06-15 — Initial release

### Features

- `MatrixClient` — Application Service client for registering users, creating
  rooms, sending events (including historical events with custom timestamps),
  uploading media, and managing room membership and state.
- `SynapseAdminClient` — Synapse Admin API client covering users
  (`listUsers`, `getUser`, `setUserAdmin`, `resetUserPassword`, `deactivateUser`,
  `getUserWhois`, `shadowBanUser`), rooms (`listRooms`, `getRoom`, `getRoomMembers`,
  `makeRoomAdmin`, `sendTombstone`, `deleteRoom`, `waitForRoomDeletion`), media
  (`purgeRemoteMedia`), registration tokens (`listRegistrationTokens`,
  `getRegistrationToken`, `createRegistrationToken`, `deleteRegistrationToken`),
  federation (`listFederationDestinations`, `getFederationDestination`), directory
  (`resolveAlias`), and server (`getServerVersion`).
- `HttpClient` / `HttpClientInterface` — minimal curl-based HTTP client with
  `get`, `post`, `put`, `delete` methods.
- `FileLogger` and `ConsoleLogger` implementing `LoggerInterface`.
- No runtime dependencies beyond PHP 8.4+ and `ext-curl`.
