<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Exception;

/**
 * Thrown when the Matrix API returns an error-coded response body.
 */
final class MatrixException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $errorMessage,
        int $httpStatus = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf( '[%s] %s', $errorCode, $errorMessage ),
            $httpStatus,
            $previous,
        );
    }
}
