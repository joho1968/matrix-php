<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Logger;

use Joho\Matrix\Contracts\LoggerInterface;

/**
 * Timestamped, level-aware file logger. Appends to the given path.
 */
final class FileLogger implements LoggerInterface
{
    private const array LEVELS = [ 'debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3 ];

    /** @var resource */
    private $handle;

    /** @param 'debug'|'info'|'warning'|'error' $minimumLevel */
    public function __construct(
        string $path,
        private readonly string $minimumLevel = 'info',
    ) {
        $handle = fopen( $path, 'a' );

        if ( $handle === false ) {
            throw new \RuntimeException( 'Cannot open log file for writing: ' . $path );
        }

        $this->handle = $handle;
    }

    public function debug( string $message, mixed ...$context ): void
    {
        $this->write( 'debug', $message, $context );
    }

    public function info( string $message, mixed ...$context ): void
    {
        $this->write( 'info', $message, $context );
    }

    public function warning( string $message, mixed ...$context ): void
    {
        $this->write( 'warning', $message, $context );
    }

    public function error( string $message, mixed ...$context ): void
    {
        $this->write( 'error', $message, $context );
    }

    /** @param array<int,mixed> $context */
    private function write( string $level, string $message, array $context ): void
    {
        if ( ( self::LEVELS[$level] ?? 0 ) < ( self::LEVELS[$this->minimumLevel] ?? 0 ) ) {
            return;
        }

        if ( $context !== [] ) {
            $message = vsprintf( $message, $context );
        }

        $line = sprintf(
            '[%s] [%s] %s' . PHP_EOL,
            date( 'Y-m-d H:i:s' ),
            strtoupper( $level ),
            $message,
        );

        fwrite( $this->handle, $line );
    }

    public function __destruct()
    {
        fclose( $this->handle );
    }
}
