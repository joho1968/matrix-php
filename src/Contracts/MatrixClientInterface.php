<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Contracts;

use Joho\Matrix\Resources\MatrixRoom;
use Joho\Matrix\Resources\MatrixUser;
use Joho\Matrix\Resources\MatrixEvent;
use Joho\Matrix\Resources\MatrixMedia;

/**
 * High-level Matrix Application Service client contract.
 */
interface MatrixClientInterface
{
    /**
     * Register a Matrix user via the AS API.
     *
     * @param array<string,mixed> $params
     */
    public function registerUser( string $localpart, array $params = [] ): MatrixUser;

    /** Retrieve a room by its Matrix room ID. */
    public function getRoom( string $roomId ): MatrixRoom;

    /**
     * Resolve a room alias to its room ID. Returns null if the alias does not exist.
     *
     * @param string $alias Full alias including sigil and server, e.g. "#name:server"
     */
    public function resolveRoomAlias( string $alias ): ?string;

    /**
     * Create a Matrix room.
     *
     * @param array<string,mixed> $params
     */
    public function createRoom( array $params, ?string $asUserId = null ): MatrixRoom;

    /**
     * Send a message event into a room.
     *
     * Provide $idempotencyKey to make the send crash-safe: the txnId is derived
     * deterministically from the key, so Synapse deduplicates a retry automatically.
     *
     * @param array<string,mixed> $content
     */
    public function sendEvent(
        string $roomId,
        string $eventType,
        array $content,
        ?string $asUserId = null,
        ?int $timestamp = null,
        ?string $idempotencyKey = null,
    ): MatrixEvent;

    /**
     * Send a state event into a room.
     *
     * @param array<string,mixed> $content
     */
    public function sendStateEvent(
        string $roomId,
        string $eventType,
        string $stateKey,
        array $content,
        ?string $asUserId = null,
    ): MatrixEvent;

    /**
     * Upload a file and return a MatrixMedia with the mxc:// URI.
     *
     * @param array<string,string> $headers
     */
    public function uploadMedia(
        string $filename,
        string $contentType,
        string $data,
        array $headers = [],
        ?string $asUserId = null,
    ): MatrixMedia;

    /** Invite a user to a room, impersonating asUserId if given. */
    public function inviteUser( string $roomId, string $userId, ?string $asUserId = null ): void;

    /** Join a room as asUserId (or the AS bot user if null). */
    public function joinRoom( string $roomId, ?string $asUserId = null ): void;

    /** Set the display name for a user. */
    public function setDisplayName( string $userId, string $displayName ): void;

    /** Set the avatar URL for a user. */
    public function setAvatarUrl( string $userId, string $mxcUrl ): void;

    /**
     * Authenticate with the homeserver using a username and password.
     *
     * Returns the access token on success. This call is unauthenticated —
     * no AS token is sent with the request.
     */
    public function login( string $username, string $password ): string;

    /**
     * Send a plain-text m.room.message event.
     *
     * Thin wrapper over sendEvent(); see its docblock for idempotency semantics.
     */
    public function sendText(
        string $roomId,
        string $body,
        ?string $asUserId = null,
        ?int $timestamp = null,
        ?string $idempotencyKey = null,
    ): MatrixEvent;

    /**
     * Send an HTML-formatted m.room.message event.
     *
     * $htmlBody is sent as formatted_body; $plainBody as the fallback body.
     */
    public function sendHtml(
        string $roomId,
        string $htmlBody,
        string $plainBody,
        ?string $asUserId = null,
        ?int $timestamp = null,
        ?string $idempotencyKey = null,
    ): MatrixEvent;
}
