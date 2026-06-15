<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Contracts;

/**
 * Represents an uploaded Matrix media resource.
 */
interface MatrixMediaInterface
{
    public function getMxcUri(): string;

    public function getFilename(): string;

    public function getContentType(): string;

    /** @return array<string,mixed> */
    public function toArray(): array;
}
