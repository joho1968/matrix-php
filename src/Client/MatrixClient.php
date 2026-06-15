<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Client;

use Joho\Matrix\Contracts\HttpClientInterface;
use Joho\Matrix\Contracts\LoggerInterface;
use Joho\Matrix\Contracts\MatrixClientInterface;
use Joho\Matrix\Exception\HttpException;
use Joho\Matrix\Exception\MatrixException;
use Joho\Matrix\Http\HttpResponse;
use Joho\Matrix\Resources\MatrixEvent;
use Joho\Matrix\Resources\MatrixMedia;
use Joho\Matrix\Resources\MatrixRoom;
use Joho\Matrix\Resources\MatrixUser;

/**
 * Application Service Matrix client.
 *
 * All mutating requests support ?user_id= impersonation. Event sends
 * additionally support ?ts= to set the server-side timestamp (Synapse
 * AS privilege only).
 */
final class MatrixClient implements MatrixClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $homeserverUrl,
        private readonly string $asToken,
        private readonly string $serverName,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function registerUser( string $localpart, array $params = [] ): MatrixUser
    {
        $body = array_merge( [ 'username' => $localpart, 'type' => 'm.login.application_service' ], $params );
        $data = $this->post( '/_matrix/client/v3/register', $body, [ 'kind' => 'user' ] );

        $userId = (string) ( $data['user_id'] ?? '@' . $localpart . ':' . $this->serverName );

        return new MatrixUser( $userId, $localpart );
    }

    public function getRoom( string $roomId ): MatrixRoom
    {
        $data = $this->get( '/_matrix/client/v3/rooms/' . rawurlencode( $roomId ) . '/state' );

        return new MatrixRoom( $roomId );
    }

    public function resolveRoomAlias( string $alias ): ?string
    {
        try {
            $data = $this->get( '/_matrix/client/v3/directory/room/' . rawurlencode( $alias ) );
            return isset( $data['room_id'] ) ? (string) $data['room_id'] : null;
        } catch ( MatrixException $e ) {
            if ( $e->errorCode === 'M_NOT_FOUND' ) {
                return null;
            }
            throw $e;
        } catch ( HttpException $e ) {
            if ( $e->response->statusCode === 404 ) {
                return null;
            }
            throw $e;
        }
    }

    public function createRoom( array $params, ?string $asUserId = null ): MatrixRoom
    {
        $data = $this->post( '/_matrix/client/v3/createRoom', $params, [], $asUserId );

        $roomId = (string) ( $data['room_id'] ?? throw new \RuntimeException( 'No room_id in createRoom response' ) );
        $alias  = isset( $data['room_alias'] ) ? (string) $data['room_alias'] : null;

        return new MatrixRoom( $roomId, $alias );
    }

    public function sendEvent(
        string $roomId,
        string $eventType,
        array $content,
        ?string $asUserId = null,
        ?int $timestamp = null,
        ?string $idempotencyKey = null,
    ): MatrixEvent {
        $txnId = $idempotencyKey !== null
            ? hash( 'sha256', $idempotencyKey )
            : $this->generateTxnId();
        $path  = sprintf(
            '/_matrix/client/v3/rooms/%s/send/%s/%s',
            rawurlencode( $roomId ),
            rawurlencode( $eventType ),
            rawurlencode( $txnId ),
        );

        $queryParams = [];
        if ( $asUserId !== null ) {
            $queryParams['user_id'] = $asUserId;
        }
        if ( $timestamp !== null ) {
            $queryParams['ts'] = (string) $timestamp;
        }

        $data    = $this->put( $path, $content, $queryParams );
        $eventId = (string) ( $data['event_id'] ?? throw new \RuntimeException( 'No event_id in sendEvent response' ) );

        return new MatrixEvent( $eventId, $roomId, $eventType, $content );
    }

    public function sendStateEvent(
        string $roomId,
        string $eventType,
        string $stateKey,
        array $content,
        ?string $asUserId = null,
    ): MatrixEvent {
        $path = sprintf(
            '/_matrix/client/v3/rooms/%s/state/%s/%s',
            rawurlencode( $roomId ),
            rawurlencode( $eventType ),
            rawurlencode( $stateKey ),
        );

        $queryParams = [];
        if ( $asUserId !== null ) {
            $queryParams['user_id'] = $asUserId;
        }

        $data    = $this->put( $path, $content, $queryParams );
        $eventId = (string) ( $data['event_id'] ?? throw new \RuntimeException( 'No event_id in sendStateEvent response' ) );

        return new MatrixEvent( $eventId, $roomId, $eventType, $content );
    }

    public function uploadMedia(
        string $filename,
        string $contentType,
        string $data,
        array $headers = [],
        ?string $asUserId = null,
    ): MatrixMedia {
        $queryParams = [ 'filename' => $filename ];
        if ( $asUserId !== null ) {
            $queryParams['user_id'] = $asUserId;
        }

        $url = $this->buildUrl( '/_matrix/media/v3/upload', $queryParams );

        $uploadHeaders = array_merge( [
            'Authorization'  => 'Bearer ' . $this->asToken,
            'Content-Type'   => $contentType,
            'Content-Length' => (string) strlen( $data ),
        ], $headers );

        // Media upload sends raw bytes, not JSON — use HTTP client directly
        $response = $this->rawPost( $url, $data, $uploadHeaders );
        $decoded  = $response->json();

        $mxcUri = (string) ( $decoded['content_uri'] ?? throw new \RuntimeException( 'No content_uri in upload response' ) );

        return new MatrixMedia( $mxcUri, $filename, $contentType );
    }

    public function inviteUser( string $roomId, string $userId, ?string $asUserId = null ): void
    {
        $path = sprintf( '/_matrix/client/v3/rooms/%s/invite', rawurlencode( $roomId ) );
        $this->post( $path, [ 'user_id' => $userId ], [], $asUserId );
    }

    public function joinRoom( string $roomId, ?string $asUserId = null ): void
    {
        $path = sprintf( '/_matrix/client/v3/join/%s', rawurlencode( $roomId ) );
        $this->post( $path, [], [], $asUserId );
    }

    public function setDisplayName( string $userId, string $displayName ): void
    {
        $path = sprintf( '/_matrix/client/v3/profile/%s/displayname', rawurlencode( $userId ) );
        $this->put( $path, [ 'displayname' => $displayName ], [ 'user_id' => $userId ] );
    }

    public function setAvatarUrl( string $userId, string $mxcUrl ): void
    {
        $path = sprintf( '/_matrix/client/v3/profile/%s/avatar_url', rawurlencode( $userId ) );
        $this->put( $path, [ 'avatar_url' => $mxcUrl ], [ 'user_id' => $userId ] );
    }

    public function login( string $username, string $password ): string
    {
        $url = $this->buildUrl( '/_matrix/client/v3/login' );

        try {
            $response = $this->httpClient->post( $url, [
                'type'     => 'm.login.password',
                'user'     => $username,
                'password' => $password,
            ] );
        } catch ( HttpException $e ) {
            $data = json_decode( $e->response->body, true ) ?? [];
            throw new MatrixException(
                (string) ( $data['errcode'] ?? 'M_UNKNOWN' ),
                (string) ( $data['error'] ?? $e->getMessage() ),
                $e->response->statusCode,
                $e,
            );
        }

        $data = $this->decode( $response );

        return (string) ( $data['access_token']
            ?? throw new \RuntimeException( 'Login response missing access_token' ) );
    }

    public function sendText(
        string $roomId,
        string $body,
        ?string $asUserId = null,
        ?int $timestamp = null,
        ?string $idempotencyKey = null,
    ): MatrixEvent {
        return $this->sendEvent(
            $roomId,
            'm.room.message',
            [ 'msgtype' => 'm.text', 'body' => $body ],
            $asUserId,
            $timestamp,
            $idempotencyKey,
        );
    }

    public function sendHtml(
        string $roomId,
        string $htmlBody,
        string $plainBody,
        ?string $asUserId = null,
        ?int $timestamp = null,
        ?string $idempotencyKey = null,
    ): MatrixEvent {
        return $this->sendEvent(
            $roomId,
            'm.room.message',
            [
                'msgtype'        => 'm.text',
                'body'           => $plainBody,
                'format'         => 'org.matrix.custom.html',
                'formatted_body' => $htmlBody,
            ],
            $asUserId,
            $timestamp,
            $idempotencyKey,
        );
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string,string> $queryParams
     * @return array<string,mixed>
     */
    private function get( string $path, array $queryParams = [], ?string $asUserId = null ): array
    {
        if ( $asUserId !== null ) {
            $queryParams['user_id'] = $asUserId;
        }

        $url = $this->buildUrl( $path, $queryParams );
        return $this->decode( $this->requestWithRetry(
            fn() => $this->httpClient->get( $url, $this->authHeader() ),
        ) );
    }

    /**
     * @param array<string,mixed>  $body
     * @param array<string,string> $queryParams
     * @return array<string,mixed>
     */
    private function post( string $path, array $body = [], array $queryParams = [], ?string $asUserId = null ): array
    {
        if ( $asUserId !== null ) {
            $queryParams['user_id'] = $asUserId;
        }

        $url = $this->buildUrl( $path, $queryParams );
        return $this->decode( $this->requestWithRetry(
            fn() => $this->httpClient->post( $url, $body, $this->authHeader() ),
        ) );
    }

    /**
     * @param array<string,mixed>  $body
     * @param array<string,string> $queryParams
     * @return array<string,mixed>
     */
    private function put( string $path, array $body = [], array $queryParams = [] ): array
    {
        $url = $this->buildUrl( $path, $queryParams );
        return $this->decode( $this->requestWithRetry(
            fn() => $this->httpClient->put( $url, $body, $this->authHeader() ),
        ) );
    }

    /**
     * Executes $fn, retrying on HTTP 429 (rate-limited) up to 3 times.
     *
     * Sleeps for retry_after_ms from the response body plus a 500 ms buffer,
     * capped at 5 minutes to avoid hanging forever on a misconfigured server.
     */
    private function requestWithRetry( callable $fn ): HttpResponse
    {
        for ( $attempt = 0; $attempt < 4; $attempt++ ) {
            try {
                return $fn();
            } catch ( HttpException $e ) {
                $data = json_decode( $e->response->body, true ) ?? [];

                if ( $attempt < 3 && in_array( $e->response->statusCode, [ 429, 503 ], true ) ) {
                    $waitMs = $e->response->statusCode === 429
                        ? min( (int) ( $data['retry_after_ms'] ?? 5_000 ), 300_000 )
                        : 5_000;
                    $this->logger?->warning( 'HTTP %d — waiting %dms before retry %d/3', $e->response->statusCode, $waitMs, $attempt + 1 );
                    usleep( ( $waitMs + 500 ) * 1_000 );
                    continue;
                }

                if ( isset( $data['errcode'] ) ) {
                    throw new MatrixException(
                        (string) $data['errcode'],
                        (string) ( $data['error'] ?? '' ),
                        $e->response->statusCode,
                        $e,
                    );
                }

                throw $e;
            }
        }

        throw new \RuntimeException( 'Rate limit retry attempts exhausted' );
    }

    /**
     * Raw POST with caller-supplied headers (used for media upload where body is not JSON).
     *
     * @param array<string,string> $headers
     */
    private function rawPost( string $url, string $body, array $headers ): HttpResponse
    {
        // Delegate to the underlying curl client by sending a single-key array
        // that encodes to the raw body. Media upload requires a different path:
        // we post bytes, not a JSON object. Use PUT on the HttpClient signature
        // with an empty body array and add Content-Type separately.
        //
        // The cleanest approach without extending the interface is to subclass
        // or call curl directly here. Since HttpClient is our own class we know
        // it only accepts arrays, so we build the request manually.
        $ch = curl_init( $url );

        if ( $ch === false ) {
            throw new \RuntimeException( 'Failed to initialise curl handle for media upload' );
        }

        $curlHeaders = [];
        foreach ( $headers as $name => $value ) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        curl_setopt_array( $ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_HEADER         => true,
        ] );

        $raw = curl_exec( $ch );

        if ( $raw === false ) {
            $error = curl_error( $ch );
            curl_close( $ch );
            throw new \RuntimeException( 'curl media upload error: ' . $error );
        }

        $headerSize = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
        $statusCode = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        $responseBody = substr( (string) $raw, $headerSize );

        return new HttpResponse( $statusCode, $responseBody, [] );
    }

    /**
     * @param array<string,string> $queryParams
     */
    private function buildUrl( string $path, array $queryParams = [] ): string
    {
        $base = rtrim( $this->homeserverUrl, '/' ) . $path;

        if ( $queryParams === [] ) {
            return $base;
        }

        return $base . '?' . http_build_query( $queryParams );
    }

    /** @return array<string,string> */
    private function authHeader(): array
    {
        return [ 'Authorization' => 'Bearer ' . $this->asToken ];
    }

    /** @return array<string,mixed> */
    private function decode( HttpResponse $response ): array
    {
        $data = $response->json();

        if ( isset( $data['errcode'] ) ) {
            throw new MatrixException(
                (string) $data['errcode'],
                (string) ( $data['error'] ?? '' ),
                $response->statusCode,
            );
        }

        return $data;
    }

    private function generateTxnId(): string
    {
        return bin2hex( random_bytes( 16 ) );
    }
}
