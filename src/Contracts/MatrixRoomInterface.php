<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Contracts;

/**
 * Represents a Matrix room resource.
 */
interface MatrixRoomInterface
{
    public function getRoomId(): string;

    public function getAlias(): ?string;

    /** @return array<string,mixed> */
    public function toArray(): array;
}
