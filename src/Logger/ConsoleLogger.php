<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Logger;

use Joho\Matrix\Contracts\LoggerInterface;

/**
 * Writes log output to STDOUT (info/debug) or STDERR (warning/error).
 */
final class ConsoleLogger implements LoggerInterface
{
    private const array LEVELS = [ 'debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3 ];

    /** @param 'debug'|'info'|'warning'|'error' $minimumLevel */
    public function __construct(
        private readonly string $minimumLevel = 'info',
    ) {}

    public function debug( string $message, mixed ...$context ): void
    {
        $this->write( 'debug', STDOUT, $message, $context );
    }

    public function info( string $message, mixed ...$context ): void
    {
        $this->write( 'info', STDOUT, $message, $context );
    }

    public function warning( string $message, mixed ...$context ): void
    {
        $this->write( 'warning', STDERR, $message, $context );
    }

    public function error( string $message, mixed ...$context ): void
    {
        $this->write( 'error', STDERR, $message, $context );
    }

    /**
     * @param resource     $stream
     * @param array<int,mixed> $context
     */
    private function write( string $level, $stream, string $message, array $context ): void
    {
        if ( ( self::LEVELS[$level] ?? 0 ) < ( self::LEVELS[$this->minimumLevel] ?? 0 ) ) {
            return;
        }

        if ( $context !== [] ) {
            $message = vsprintf( $message, $context );
        }

        fwrite( $stream, sprintf( '[%s] %s' . PHP_EOL, strtoupper( $level ), $message ) );
    }
}
