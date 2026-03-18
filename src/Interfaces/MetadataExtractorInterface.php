<?php

declare(strict_types=1);

namespace mvbplugins\Interfaces;

interface MetadataExtractorInterface
{
    /**
     * Extract file-based metadata from an image file.
     *
     * Preferred source priority:
     * 1. XMP
     * 2. IPTC
     * 3. EXIF
     * 4. File-derived technical properties
     *
     * @return array{
     *     mime?: string, // TODO: decide to keep this or not
     *     ext?: string,
     *     width?: int,
     *     height?: int,
     *     orientation?: int,
     *
     *     make?: string,
     *     model?: string,
     *     camera?: string,
     *     lens?: string,
     *     shutter_speed?: float|string,
     *     aperture?: float,
     *     iso?: int,
     *     datetime_original?: string,
     *     created_timestamp?: int,
     *     focal_length?: float,
     *     focal_length_in_35mm?: int,
     *
     *     gps?: array{
     *         lat?: float,
     *         lon?: float,
     *         altitude?: float
     *     },
     *
     *     title?: string,
     *     headline?: string,F
     *     caption?: string,
     *     description?: string,
     *     creator?: string,
     *     credit?: string,
     *     copyright?: string,
     *     keywords?: list<string>
     * }
     */
    public function getMetadata(string $file): array;

    /**
     * @return list<string>
     */
    public function getSupportedFileTypes(): array;

    public function isFileSupported(string $file): bool;
}