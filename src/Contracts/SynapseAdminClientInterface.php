<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Contracts;

/**
 * Synapse Admin API client contract (/_synapse/admin/* endpoints).
 */
interface SynapseAdminClientInterface
{
    // -------------------------------------------------------------------------
    // Users
    // -------------------------------------------------------------------------

    /**
     * List local user accounts.
     *
     * @return array{users: array<int, array<string, mixed>>, next_token: string|null, total: int}
     * @throws \RuntimeException on HTTP failure
     */
    public function listUsers( int $limit = 100, int $from = 0, bool $guests = false, bool $deactivated = false ): array;

    /**
     * Fetch details for a single user.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException on HTTP failure
     */
    public function getUser( string $userId ): array;

    /**
     * Grant or revoke Synapse admin status for a user.
     *
     * @throws \RuntimeException on HTTP failure
     */
    public function setUserAdmin( string $userId, bool $admin ): void;

    /**
     * Reset a user's password and optionally invalidate all their sessions.
     *
     * @throws \RuntimeException on HTTP failure
     */
    public function resetUserPassword( string $userId, string $newPassword, bool $logoutDevices = true ): void;

    /**
     * Deactivate a Matrix user. With $erase=true, also redacts their messages.
     *
     * @throws \RuntimeException on HTTP failure
     */
    public function deactivateUser( string $userId, bool $erase = true ): void;

    /**
     * Return active sessions, devices, and last-seen IPs for a user.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException on HTTP failure
     */
    public function getUserWhois( string $userId ): array;

    /**
     * Shadow-ban or un-shadow-ban a user.
     *
     * Shadow-banned users see their own messages but no one else does.
     *
     * @throws \RuntimeException on HTTP failure
     */
    public function shadowBanUser( string $userId, bool $ban = true ): void;

    // -------------------------------------------------------------------------
    // Rooms
    // -------------------------------------------------------------------------

    /**
     * List rooms on the homeserver.
     *
     * @return array{rooms: array<int, array<string, mixed>>, offset: int, total_rooms: int, next_batch: int|null}
     * @throws \RuntimeException on HTTP failure
     */
    public function listRooms( int $limit = 100, int $from = 0 ): array;

    /**
     * Fetch details for a single room.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException on HTTP failure
     */
    public function getRoom( string $roomId ): array;

    /**
     * Send an m.room.tombstone state event marking $roomId as superseded by $replacementRoomId.
     *
     * The admin token user must have sufficient power level in the room.
     *
     * @throws \RuntimeException on HTTP failure
     */
    public function sendTombstone( string $roomId, string $replacementRoomId, string $body = 'This room has been replaced.' ): void;

    /**
     * Kick all users and schedule a room for deletion.
     * Returns the delete_id to pass to waitForRoomDeletion().
     *
     * @throws \RuntimeException on HTTP failure
     */
    public function deleteRoom( string $roomId, bool $purge = true ): string;

    /**
     * Block until a room deletion task completes.
     *
     * @throws \RuntimeException if the deletion fails or times out
     */
    public function waitForRoomDeletion( string $deleteId, int $maxSeconds = 300 ): void;

    /**
     * List members of a room.
     *
     * @return array{members: array<int, string>, total: int}
     * @throws \RuntimeException on HTTP failure
     */
    public function getRoomMembers( string $roomId ): array;

    /**
     * Promote a local user to room admin, even if they are not currently a member.
     *
     * @throws \RuntimeException on HTTP failure
     */
    public function makeRoomAdmin( string $roomId, string $userId ): void;

    // -------------------------------------------------------------------------
    // Directory
    // -------------------------------------------------------------------------

    /**
     * Resolve a room alias (#alias:server) to a room ID, or return null if not found.
     *
     * Uses the standard client API endpoint, not the admin API.
     *
     * @throws \RuntimeException on non-404 HTTP failure
     */
    public function resolveAlias( string $alias ): ?string;

    // -------------------------------------------------------------------------
    // Media
    // -------------------------------------------------------------------------

    /**
     * Delete cached remote media older than $beforeTimestamp (Unix ms).
     *
     * Only affects media fetched from other homeservers, not locally uploaded content.
     * Returns the number of files deleted.
     *
     * @throws \RuntimeException on HTTP failure
     */
    public function purgeRemoteMedia( int $beforeTimestamp ): int;

    // -------------------------------------------------------------------------
    // Registration tokens
    // -------------------------------------------------------------------------

    /**
     * List all registration tokens.
     *
     * @return array<int, array<string, mixed>>
     * @throws \RuntimeException on HTTP failure
     */
    public function listRegistrationTokens(): array;

    /**
     * Fetch a single registration token.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException on HTTP failure
     */
    public function getRegistrationToken( string $token ): array;

    /**
     * Create a new registration token.
     *
     * Omit $token to let Synapse generate one. $usesAllowed=null means unlimited.
     * $expiryTime is a Unix timestamp in milliseconds; null means no expiry.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException on HTTP failure
     */
    public function createRegistrationToken( ?string $token = null, ?int $usesAllowed = null, ?int $expiryTime = null ): array;

    /**
     * Delete a registration token.
     *
     * @throws \RuntimeException on HTTP failure
     */
    public function deleteRegistrationToken( string $token ): void;

    // -------------------------------------------------------------------------
    // Federation
    // -------------------------------------------------------------------------

    /**
     * List known federation destinations.
     *
     * @return array{destinations: array<int, array<string, mixed>>, total: int, next_token: string|null}
     * @throws \RuntimeException on HTTP failure
     */
    public function listFederationDestinations( int $limit = 100, int $from = 0 ): array;

    /**
     * Fetch details for a single federation destination.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException on HTTP failure
     */
    public function getFederationDestination( string $serverName ): array;

    // -------------------------------------------------------------------------
    // Server
    // -------------------------------------------------------------------------

    /**
     * Return Synapse and Python version strings.
     *
     * @return array{server_version: string, python_version: string}
     * @throws \RuntimeException on HTTP failure
     */
    public function getServerVersion(): array;
}
