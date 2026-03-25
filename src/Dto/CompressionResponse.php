<?php

namespace PicDiet\Dto;

use PicDiet\Enum\ImageFormatEnum;

/**
 * DTO representing the result of an image compression operation.
 *
 * Use the named constructors {@see self::success()} and {@see self::failure()}
 * to instantiate — the constructor is private to prevent incoherent states
 * (e.g. success: true with path: null).
 *
 * {@see self::$format} is guaranteed non-null on success. On failure it is null
 * unless the caller knew the intended format at the point of the error.
 *
 * @author Stefano Perrini <perrini.stefano@gmail.com> aka La Matrigna
 */
class CompressionResponse
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $path,
        public readonly ?string $error,
        public readonly int $originalSize,
        public readonly int $compressedSize,
        public readonly ?ImageFormatEnum $format,
        public readonly ?string $compressedFileName,
        public readonly ?string $outputDirectory,
    ) {
    }

    public static function success(
        string $path,
        int $originalSize,
        int $compressedSize,
        ImageFormatEnum $format,
        string $compressedFileName,
        string $outputDirectory,
    ): self {
        return new self(
            success: true,
            path: $path,
            error: null,
            originalSize: $originalSize,
            compressedSize: $compressedSize,
            format: $format,
            compressedFileName: $compressedFileName,
            outputDirectory: $outputDirectory,
        );
    }

    public static function failure(
        string $error,
        ?ImageFormatEnum $format = null,
        int $originalSize = 0,
    ): self {
        return new self(
            success: false,
            path: null,
            error: $error,
            originalSize: $originalSize,
            compressedSize: 0,
            format: $format,
            compressedFileName: null,
            outputDirectory: null,
        );
    }
}
