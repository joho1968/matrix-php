<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Contracts;

use Joho\Matrix\Http\HttpResponse;

/**
 * Minimal HTTP client contract used by the Matrix client layer.
 */
interface HttpClientInterface
{
    /** @param array<string,string> $headers */
    public function get( string $url, array $headers = [] ): HttpResponse;

    /**
     * @param array<string,mixed>  $body
     * @param array<string,string> $headers
     */
    public function post( string $url, array $body = [], array $headers = [] ): HttpResponse;

    /**
     * @param array<string,mixed>  $body
     * @param array<string,string> $headers
     */
    public function put( string $url, array $body = [], array $headers = [] ): HttpResponse;

    /**
     * @param array<string,mixed>  $body
     * @param array<string,string> $headers
     */
    public function delete( string $url, array $body = [], array $headers = [] ): HttpResponse;
}
