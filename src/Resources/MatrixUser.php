<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Resources;

use Joho\Matrix\Contracts\MatrixUserInterface;

/** Immutable value object representing a Matrix user. */
final class MatrixUser implements MatrixUserInterface
{
    public function __construct(
        private readonly string $userId,
        private readonly string $localpart,
        private readonly ?string $displayName = null,
    ) {}

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getLocalpart(): string
    {
        return $this->localpart;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'user_id'      => $this->userId,
            'localpart'    => $this->localpart,
            'display_name' => $this->displayName,
        ];
    }
}
