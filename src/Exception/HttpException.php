<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Exception;

use Joho\Matrix\Http\HttpResponse;

/**
 * Thrown when an HTTP request returns a non-2xx status code.
 */
final class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly HttpResponse $response,
    ) {
        $body = trim( $response->body );
        $preview = str_starts_with( $body, '{' ) || str_starts_with( $body, '[' )
            ? $body
            : ( strlen( $body ) > 120 ? substr( $body, 0, 120 ) . '...' : $body );

        parent::__construct(
            sprintf( 'HTTP %d: %s', $response->statusCode, $preview ),
            $response->statusCode,
        );
    }
}
