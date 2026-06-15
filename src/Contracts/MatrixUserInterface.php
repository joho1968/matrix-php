<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Contracts;

/**
 * Represents a Matrix user resource.
 */
interface MatrixUserInterface
{
    public function getUserId(): string;

    public function getLocalpart(): string;

    public function getDisplayName(): ?string;

    /** @return array<string,mixed> */
    public function toArray(): array;
}
