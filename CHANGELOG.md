# Changelog

All notable changes to matrix-php will be documented here.

## [0.92.0] — 2026-06-15

### Added

- `listRooms()` gains an optional `$searchTerm` parameter passed as `search_term` to
  the Synapse Admin API (matches room name, canonical alias, and room ID).
- `whoami(): string` — returns the Matrix user ID of the admin token owner.
- `leaveRoom( string $roomId ): void` — leaves a room as the admin token user.
- `kickRoomMember( string $roomId, string $userId, string $reason )` — kicks a user
  from a room via the Matrix client kick endpoint.
- `setRoomPowerLevel( string $roomId, string $userId, int $level )` — sets a user's
  power level by reading and rewriting `m.room.power_levels`; unsets the user entry
  when the level equals `users_default`.
- `forceJoinRoom( string $roomId, string $userId )` — accepts a pending invite and joins
  a room as the admin token user via `POST /_matrix/client/v3/rooms/{roomId}/join`.
  Call `makeRoomAdmin()` first so a valid invite exists; throws on failure.

## [0.90.4] — 2026-06-15

### Fixed

- `getStateEvent()` now falls back to `GET /_synapse/admin/v1/rooms/{roomId}/state`
  when the client API returns 403 (admin token user is not a member of the room).
  This means retention and other state events are correctly fetched for all rooms
  regardless of membership, without the overhead of the admin endpoint for rooms
  the admin user is already in.

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
