<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright 2026 Joaquim Homrighausen; all rights reserved.

namespace Joho\Matrix\Resources;

use Joho\Matrix\Contracts\MatrixMediaInterface;

/** Immutable value object representing an uploaded Matrix media file. */
final class MatrixMedia implements MatrixMediaInterface
{
    public function __construct(
        private readonly string $mxcUri,
        private readonly string $filename,
        private readonly string $contentType,
    ) {}

    public function getMxcUri(): string
    {
        return $this->mxcUri;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'content_uri'  => $this->mxcUri,
            'filename'     => $this->filename,
            'content_type' => $this->contentType,
        ];
    }
}
