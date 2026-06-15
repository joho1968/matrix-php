<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Contracts;

/**
 * PSR-3-inspired logger contract without the PSR-3 dependency.
 */
interface LoggerInterface
{
    public function debug( string $message, mixed ...$context ): void;

    public function info( string $message, mixed ...$context ): void;

    public function warning( string $message, mixed ...$context ): void;

    public function error( string $message, mixed ...$context ): void;
}
