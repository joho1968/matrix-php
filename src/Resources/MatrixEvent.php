<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Resources;

use Joho\Matrix\Contracts\MatrixEventInterface;

/** Immutable value object representing a sent Matrix event. */
final class MatrixEvent implements MatrixEventInterface
{
    public function __construct(
        private readonly string $eventId,
        private readonly string $roomId,
        private readonly string $type,
        /** @var array<string,mixed> */
        private readonly array $content,
    ) {}

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getRoomId(): string
    {
        return $this->roomId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /** @return array<string,mixed> */
    public function getContent(): array
    {
        return $this->content;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'room_id'  => $this->roomId,
            'type'     => $this->type,
            'content'  => $this->content,
        ];
    }
}
