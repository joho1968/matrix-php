<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Http;

use Joho\Matrix\Contracts\HttpClientInterface;
use Joho\Matrix\Exception\HttpException;

/**
 * curl-only HTTP client. Throws HttpException on non-2xx responses.
 */
final class HttpClient implements HttpClientInterface
{
    private const TIMEOUT_SECONDS = 30;

    /** @param array<string,string> $defaultHeaders */
    public function __construct(
        private readonly array $defaultHeaders = [],
    ) {}

    /** @param array<string,string> $headers */
    public function get( string $url, array $headers = [] ): HttpResponse
    {
        return $this->request( 'GET', $url, null, $headers );
    }

    /**
     * @param array<string,mixed>  $body
     * @param array<string,string> $headers
     */
    public function post( string $url, array $body = [], array $headers = [] ): HttpResponse
    {
        return $this->request( 'POST', $url, $body, $headers );
    }

    /**
     * @param array<string,mixed>  $body
     * @param array<string,string> $headers
     */
    public function put( string $url, array $body = [], array $headers = [] ): HttpResponse
    {
        return $this->request( 'PUT', $url, $body, $headers );
    }

    /**
     * @param array<string,mixed>  $body
     * @param array<string,string> $headers
     */
    public function delete( string $url, array $body = [], array $headers = [] ): HttpResponse
    {
        return $this->request( 'DELETE', $url, $body === [] ? null : $body, $headers );
    }

    /**
     * @param array<string,mixed>|null $body
     * @param array<string,string>     $headers
     */
    private function request( string $method, string $url, ?array $body, array $headers ): HttpResponse
    {
        $ch = curl_init();

        if ( $ch === false ) {
            throw new \RuntimeException( 'Failed to initialise curl handle' );
        }

        $mergedHeaders = array_merge( $this->defaultHeaders, $headers );

        $curlHeaders = [];
        foreach ( $mergedHeaders as $name => $value ) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        $encodedBody = null;
        if ( $body !== null ) {
            // An empty PHP array encodes as [] but Matrix requires {}; cast to object.
            $encodedBody = json_encode( $body === [] ? new \stdClass() : $body, JSON_THROW_ON_ERROR );
            $curlHeaders[] = 'Content-Type: application/json';
            $curlHeaders[] = 'Content-Length: ' . strlen( $encodedBody );
        }

        curl_setopt_array( $ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_HEADER         => true,
        ] );

        match ( $method ) {
            'GET'  => null,
            'POST' => curl_setopt_array( $ch, [
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => $encodedBody ?? '',
            ] ),
            'PUT'    => curl_setopt_array( $ch, [
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS    => $encodedBody ?? '',
            ] ),
            'DELETE' => curl_setopt_array( $ch, [
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_POSTFIELDS    => $encodedBody ?? '',
            ] ),
            default => throw new \InvalidArgumentException( 'Unsupported HTTP method: ' . $method ),
        };

        $raw = curl_exec( $ch );

        if ( $raw === false ) {
            $error = curl_error( $ch );
            curl_close( $ch );
            throw new \RuntimeException( 'curl error: ' . $error );
        }

        $headerSize = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
        $statusCode = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        curl_close( $ch );

        $rawHeaders = substr( (string) $raw, 0, $headerSize );
        $responseBody = substr( (string) $raw, $headerSize );
        $parsedHeaders = $this->parseHeaders( $rawHeaders );

        $response = new HttpResponse( $statusCode, $responseBody, $parsedHeaders );

        if ( !$response->isSuccess() ) {
            throw new HttpException( $response );
        }

        return $response;
    }

    /**
     * Parse raw HTTP header block into an associative array.
     *
     * @return array<string,string>
     */
    private function parseHeaders( string $raw ): array
    {
        $headers = [];

        foreach ( explode( "\r\n", $raw ) as $line ) {
            $pos = strpos( $line, ':' );
            if ( $pos === false ) {
                continue;
            }
            $name = strtolower( trim( substr( $line, 0, $pos ) ) );
            $value = trim( substr( $line, $pos + 1 ) );
            $headers[$name] = $value;
        }

        return $headers;
    }
}
