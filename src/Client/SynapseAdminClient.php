<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Client;

use Joho\Matrix\Contracts\HttpClientInterface;
use Joho\Matrix\Contracts\LoggerInterface;
use Joho\Matrix\Contracts\SynapseAdminClientInterface;

/**
 * Synapse-specific Admin API client for destructive operations.
 *
 * Requires an admin user's access token (not the AS token). All operations
 * target the /_synapse/admin/* endpoints which are Synapse-only and not
 * part of the Matrix specification.
 */
final class SynapseAdminClient implements SynapseAdminClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $homeserverUrl,
        private readonly string $adminToken,
        private readonly ?LoggerInterface $logger = null,
        private readonly int $pollIntervalSeconds = 2,
    ) {}

    // -------------------------------------------------------------------------
    // Users
    // -------------------------------------------------------------------------

    /**
     * List local user accounts, optionally including guests and/or deactivated users.
     *
     * @return array{users: array<int, array<string, mixed>>, next_token: string|null, total: int}
     */
    public function listUsers( int $limit = 100, int $from = 0, bool $guests = false, bool $deactivated = false ): array
    {
        $url = $this->url( '/_synapse/admin/v2/users?' . http_build_query( [
            'limit'       => $limit,
            'from'        => $from,
            'guests'      => $guests ? 'true' : 'false',
            'deactivated' => $deactivated ? 'true' : 'false',
        ] ) );

        return $this->http->get( $url, $this->authHeaders() )->json();
    }

    /**
     * Fetch details for a single user.
     *
     * @return array<string, mixed>
     */
    public function getUser( string $userId ): array
    {
        $url = $this->url( '/_synapse/admin/v2/users/' . urlencode( $userId ) );
        return $this->http->get( $url, $this->authHeaders() )->json();
    }

    /**
     * Grant or revoke Synapse server admin status for a user.
     */
    public function setUserAdmin( string $userId, bool $admin ): void
    {
        $url = $this->url( '/_synapse/admin/v2/users/' . urlencode( $userId ) );
        $this->http->put( $url, [ 'admin' => $admin ], $this->authHeaders() );
    }

    /**
     * Reset a user's password via the Synapse Admin API.
     *
     * With $logoutDevices=true (default), all existing sessions are invalidated.
     */
    public function resetUserPassword( string $userId, string $newPassword, bool $logoutDevices = true ): void
    {
        $url = $this->url( '/_synapse/admin/v1/reset_password/' . urlencode( $userId ) );
        $this->http->post( $url, [ 'new_password' => $newPassword, 'logout_devices' => $logoutDevices ], $this->authHeaders() );
    }

    // -------------------------------------------------------------------------
    // Rooms
    // -------------------------------------------------------------------------

    /**
     * List rooms on the homeserver.
     *
     * @return array{rooms: array<int, array<string, mixed>>, offset: int, total_rooms: int, next_batch: int|null}
     */
    public function listRooms( int $limit = 100, int $from = 0 ): array
    {
        $url = $this->url( '/_synapse/admin/v1/rooms?' . http_build_query( [
            'limit' => $limit,
            'from'  => $from,
        ] ) );

        return $this->http->get( $url, $this->authHeaders() )->json();
    }

    /**
     * Fetch details for a single room.
     *
     * @return array<string, mixed>
     */
    public function getRoom( string $roomId ): array
    {
        $url = $this->url( '/_synapse/admin/v1/rooms/' . urlencode( $roomId ) );
        return $this->http->get( $url, $this->authHeaders() )->json();
    }

    /**
     * List members of a room.
     *
     * @return array{members: array<int, string>, total: int}
     */
    public function getRoomMembers( string $roomId ): array
    {
        $url = $this->url( '/_synapse/admin/v1/rooms/' . urlencode( $roomId ) . '/members' );
        return $this->http->get( $url, $this->authHeaders() )->json();
    }

    /**
     * Promote a local user to room admin via the Synapse admin API.
     */
    public function makeRoomAdmin( string $roomId, string $userId ): void
    {
        $url = $this->url( '/_synapse/admin/v1/rooms/' . urlencode( $roomId ) . '/make_room_admin' );
        $this->http->post( $url, [ 'user_id' => $userId ], $this->authHeaders() );
    }

    /**
     * Send an arbitrary state event to a room via the Matrix client API.
     */
    public function sendStateEvent( string $roomId, string $eventType, string $stateKey, array $content ): void
    {
        $url = $this->url(
            '/_matrix/client/v3/rooms/' . rawurlencode( $roomId )
            . '/state/' . rawurlencode( $eventType )
            . '/' . rawurlencode( $stateKey ),
        );
        $this->http->put( $url, $content, $this->authHeaders() );
    }

    /**
     * Fetch a single state event from a room. Returns null if not found (404).
     *
     * @return array<string, mixed>|null
     */
    public function getStateEvent( string $roomId, string $eventType, string $stateKey = '' ): ?array
    {
        $url = $this->url(
            '/_matrix/client/v3/rooms/' . rawurlencode( $roomId )
            . '/state/' . rawurlencode( $eventType )
            . '/' . rawurlencode( $stateKey ),
        );
        try {
            return $this->http->get( $url, $this->authHeaders() )->json();
        } catch ( \Joho\Matrix\Exception\HttpException $e ) {
            if ( $e->response->statusCode === 404 ) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Send an m.room.tombstone state event via the Matrix client API using the admin token.
     *
     * The admin token user must be a member of $roomId with sufficient power level (≥ 100 for tombstone).
     *
     * @throws \RuntimeException on HTTP failure
     */
    public function sendTombstone( string $roomId, string $replacementRoomId, string $body = 'This room has been replaced.' ): void
    {
        $this->sendStateEvent( $roomId, 'm.room.tombstone', '', [
            'body'             => $body,
            'replacement_room' => $replacementRoomId,
        ] );
    }

    /**
     * Kick all users and schedule a room for deletion.
     *
     * Returns the delete_id to pass to waitForRoomDeletion().
     * Uses the v2 async endpoint — the actual purge runs in the background.
     *
     * @throws \RuntimeException on HTTP failure
     */
    public function deleteRoom( string $roomId, bool $purge = true ): string
    {
        $url  = $this->url( '/_synapse/admin/v2/rooms/' . urlencode( $roomId ) );
        $body = [ 'purge' => $purge, 'block' => false ];

        $this->logger?->debug( 'Requesting deletion of room %s (purge=%s)', $roomId, $purge ? 'true' : 'false' );

        $response = $this->http->delete( $url, $body, $this->authHeaders() );
        $data     = $response->json();

        if ( !isset( $data['delete_id'] ) || !is_string( $data['delete_id'] ) ) {
            throw new \RuntimeException( 'Unexpected response from room delete: ' . json_encode( $data ) );
        }

        return $data['delete_id'];
    }

    /**
     * Block until a room deletion task completes, polling every pollIntervalSeconds.
     *
     * @throws \RuntimeException if the deletion fails or times out
     */
    public function waitForRoomDeletion( string $deleteId, int $maxSeconds = 300 ): void
    {
        $deadline = time() + $maxSeconds;
        $url      = $this->url( '/_synapse/admin/v2/rooms/delete_status/' . urlencode( $deleteId ) );

        while ( time() < $deadline ) {
            $response = $this->http->get( $url, $this->authHeaders() );
            $data     = $response->json();
            $status   = (string) ( $data['status'] ?? 'unknown' );

            $this->logger?->debug( 'Room deletion %s status: %s', $deleteId, $status );

            if ( $status === 'complete' ) {
                return;
            }

            if ( $status === 'failed' ) {
                throw new \RuntimeException( 'Room deletion task failed (delete_id: ' . $deleteId . ')' );
            }

            if ( $this->pollIntervalSeconds > 0 ) {
                sleep( $this->pollIntervalSeconds );
            }
        }

        throw new \RuntimeException( sprintf(
            'Room deletion timed out after %d seconds (delete_id: %s)',
            $maxSeconds,
            $deleteId,
        ) );
    }

    /**
     * Deactivate a Matrix user account.
     *
     * With $erase=true, Synapse also redacts all their messages and removes
     * their profile. This is irreversible.
     *
     * @throws \RuntimeException on HTTP failure
     */
    public function deactivateUser( string $userId, bool $erase = true ): void
    {
        $url = $this->url( '/_synapse/admin/v1/deactivate/' . urlencode( $userId ) );

        $this->logger?->debug( 'Deactivating user %s (erase=%s)', $userId, $erase ? 'true' : 'false' );

        $this->http->post( $url, [ 'erase' => $erase ], $this->authHeaders() );
    }

    /**
     * Return active sessions, devices, and last-seen connection info for a user.
     *
     * @return array<string, mixed>
     */
    public function getUserWhois( string $userId ): array
    {
        $url = $this->url( '/_synapse/admin/v1/whois/' . urlencode( $userId ) );
        return $this->http->get( $url, $this->authHeaders() )->json();
    }

    /**
     * Shadow-ban ($ban=true) or un-shadow-ban ($ban=false) a user.
     */
    public function shadowBanUser( string $userId, bool $ban = true ): void
    {
        $url = $this->url( '/_synapse/admin/v1/users/' . urlencode( $userId ) . '/shadow_ban' );

        if ( $ban ) {
            $this->http->post( $url, [], $this->authHeaders() );
        } else {
            $this->http->delete( $url, [], $this->authHeaders() );
        }
    }

    // -------------------------------------------------------------------------
    // Media
    // -------------------------------------------------------------------------

    /**
     * Delete cached remote media older than $beforeTimestamp (Unix ms).
     * Returns the number of files deleted.
     */
    public function purgeRemoteMedia( int $beforeTimestamp ): int
    {
        $url  = $this->url( '/_synapse/admin/v1/purge_media_cache?before_ts=' . $beforeTimestamp );
        $data = $this->http->post( $url, [], $this->authHeaders() )->json();
        return (int) ( $data['deleted'] ?? 0 );
    }

    // -------------------------------------------------------------------------
    // Registration tokens
    // -------------------------------------------------------------------------

    /**
     * List all registration tokens.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRegistrationTokens(): array
    {
        $url  = $this->url( '/_synapse/admin/v1/registration_tokens' );
        $data = $this->http->get( $url, $this->authHeaders() )->json();
        return $data['registration_tokens'] ?? [];
    }

    /**
     * Fetch a single registration token.
     *
     * @return array<string, mixed>
     */
    public function getRegistrationToken( string $token ): array
    {
        $url = $this->url( '/_synapse/admin/v1/registration_tokens/' . rawurlencode( $token ) );
        return $this->http->get( $url, $this->authHeaders() )->json();
    }

    /**
     * Create a new registration token.
     *
     * @return array<string, mixed>
     */
    public function createRegistrationToken( ?string $token = null, ?int $usesAllowed = null, ?int $expiryTime = null ): array
    {
        $url  = $this->url( '/_synapse/admin/v1/registration_tokens/new' );
        $body = array_filter( [
            'token'        => $token,
            'uses_allowed' => $usesAllowed,
            'expiry_time'  => $expiryTime,
        ], fn ( $v ) => $v !== null );

        return $this->http->post( $url, $body, $this->authHeaders() )->json();
    }

    /**
     * Delete a registration token.
     */
    public function deleteRegistrationToken( string $token ): void
    {
        $url = $this->url( '/_synapse/admin/v1/registration_tokens/' . rawurlencode( $token ) );
        $this->http->delete( $url, [], $this->authHeaders() );
    }

    // -------------------------------------------------------------------------
    // Federation
    // -------------------------------------------------------------------------

    /**
     * List known federation destinations.
     *
     * @return array{destinations: array<int, array<string, mixed>>, total: int, next_token: string|null}
     */
    public function listFederationDestinations( int $limit = 100, int $from = 0 ): array
    {
        $url = $this->url( '/_synapse/admin/v1/federation/destinations?' . http_build_query( [
            'limit' => $limit,
            'from'  => $from,
        ] ) );
        return $this->http->get( $url, $this->authHeaders() )->json();
    }

    /**
     * Fetch details for a single federation destination.
     *
     * @return array<string, mixed>
     */
    public function getFederationDestination( string $serverName ): array
    {
        $url = $this->url( '/_synapse/admin/v1/federation/destinations/' . rawurlencode( $serverName ) );
        return $this->http->get( $url, $this->authHeaders() )->json();
    }

    // -------------------------------------------------------------------------
    // Directory
    // -------------------------------------------------------------------------

    /**
     * Resolve a room alias to a room ID via the standard client directory endpoint.
     * Returns null if the alias is not found (404).
     */
    public function resolveAlias( string $alias ): ?string
    {
        $url = $this->url( '/_matrix/client/v3/directory/room/' . rawurlencode( $alias ) );

        try {
            $data = $this->http->get( $url, $this->authHeaders() )->json();
            return isset( $data['room_id'] ) ? (string) $data['room_id'] : null;
        } catch ( \Joho\Matrix\Exception\HttpException $e ) {
            if ( $e->response->statusCode === 404 ) {
                return null;
            }
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Server
    // -------------------------------------------------------------------------

    /**
     * Return the Synapse and Python version strings.
     *
     * @return array{server_version: string, python_version: string}
     */
    public function getServerVersion(): array
    {
        $url = $this->url( '/_synapse/admin/v1/server_version' );
        return $this->http->get( $url, $this->authHeaders() )->json();
    }

    /** @return array<string,string> */
    private function authHeaders(): array
    {
        return [ 'Authorization' => 'Bearer ' . $this->adminToken ];
    }

    private function url( string $path ): string
    {
        return rtrim( $this->homeserverUrl, '/' ) . $path;
    }
}
