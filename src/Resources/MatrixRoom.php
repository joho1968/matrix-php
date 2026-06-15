<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Resources;

use Joho\Matrix\Contracts\MatrixRoomInterface;

/** Immutable value object representing a Matrix room. */
final class MatrixRoom implements MatrixRoomInterface
{
    public function __construct(
        private readonly string $roomId,
        private readonly ?string $alias = null,
    ) {}

    public function getRoomId(): string
    {
        return $this->roomId;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'room_id' => $this->roomId,
            'alias'   => $this->alias,
        ];
    }
}
