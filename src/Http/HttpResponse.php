<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Http;

/**
 * Immutable value object representing a raw HTTP response.
 */
final class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        /** @var array<string,string> */
        public readonly array $headers,
    ) {}

    /**
     * Decode the body as JSON and return the resulting array.
     *
     * @return array<string,mixed>
     */
    public function json(): array
    {
        try {
            $decoded = json_decode( $this->body, true, 512, JSON_THROW_ON_ERROR );
        } catch ( \JsonException $e ) {
            throw new \RuntimeException(
                sprintf(
                    'Non-JSON response (HTTP %d): %s',
                    $this->statusCode,
                    substr( $this->body, 0, 300 ),
                ),
                0,
                $e,
            );
        }

        if ( !is_array( $decoded ) ) {
            throw new \RuntimeException( 'Expected JSON object in response body' );
        }

        return $decoded;
    }

    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
