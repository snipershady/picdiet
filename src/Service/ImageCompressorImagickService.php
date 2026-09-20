<?php

declare(strict_types=1);

namespace PicDiet\Service;

use PicDiet\Dto\CompressionResponse;
use PicDiet\Enum\ImageFormatEnum;

/**
 * Imagick-based image compressor.
 *
 * Requires the PHP imagick extension. Use {@see ImageCompressorFactory}
 * to instantiate — it verifies extension availability before construction.
 *
 * Advantages over the GD backend: higher-quality resampling (Lanczos),
 * automatic EXIF stripping, animated GIF support, and AVIF/HEIC input.
 *
 * @author Stefano Perrini <perrini.stefano@gmail.com> aka La Matrigna
 */
class ImageCompressorImagickService extends AbstractImageCompressorService
{
    #[\Override]
    public function compress(
        string $sourcePath,
        ImageFormatEnum $imageFormatEnum = ImageFormatEnum::WEBP,
        int $maxWidth = self::MAX_WIDTH,
        int $maxHeight = self::MAX_HEIGHT,
        ?int $quality = null,
        ?string $outputDirectory = null,
    ): CompressionResponse {
        $this->validateArguments($maxWidth, $maxHeight, $quality, $outputDirectory);

        $quality ??= self::DEFAULT_QUALITY;

        if (!file_exists($sourcePath)) {
            return CompressionResponse::failure('Source file does not exist');
        }

        $originalSize = filesize($sourcePath);

        if (false === $originalSize) {
            return CompressionResponse::failure('Failed to read source file size');
        }

        try {
            $imagick = new \Imagick($sourcePath);
        } catch (\ImagickException $imagickException) {
            return CompressionResponse::failure('Invalid image file, Exception: ' . $imagickException->getMessage(), $originalSize, $imageFormatEnum);
        }

        return $this->processImage(
            sourcePath: $sourcePath,
            imagick: $imagick,
            originalSize: $originalSize,
            imageFormatEnum: $imageFormatEnum,
            maxWidth: $maxWidth,
            maxHeight: $maxHeight,
            quality: $quality,
            outputDirectory: $outputDirectory ?? dirname($sourcePath),
        );
    }

    /**
     * Executes the full Imagick transformation pipeline: EXIF stripping,
     * resize, format conversion, quality, saving, and size verification.
     */
    private function processImage(
        string $sourcePath,
        \Imagick $imagick,
        int $originalSize,
        ImageFormatEnum $imageFormatEnum,
        int $maxWidth,
        int $maxHeight,
        int $quality,
        string $outputDirectory,
    ): CompressionResponse {
        // Strip EXIF and other metadata to reduce output size
        $imagick->stripImage();

        // Resize only if the image exceeds the max dimensions
        if ($imagick->getImageWidth() > $maxWidth || $imagick->getImageHeight() > $maxHeight) {
            $imagick->thumbnailImage($maxWidth, $maxHeight, bestfit: true);
        }

        $imagick->setImageFormat($this->imagickFormat($imageFormatEnum));
        $imagick->setImageCompressionQuality($quality);

        $compressedFileName = pathinfo($sourcePath, PATHINFO_FILENAME) . '_compressed.' . $imageFormatEnum->value;
        $outputPath = $outputDirectory . '/' . $compressedFileName;

        try {
            $saveCheck = $imagick->writeImage($outputPath);
        } catch (\ImagickException $imagickException) {
            $imagick->clear();
            $imagick->destroy();

            return CompressionResponse::failure('Failed to save compressed image, Exception: ' . $imagickException->getMessage(), $originalSize, $imageFormatEnum);
        }

        $imagick->clear();
        $imagick->destroy();

        if (!$saveCheck) {
            return CompressionResponse::failure('Failed to save compressed image', $originalSize, $imageFormatEnum);
        }

        $compressedSize = filesize($outputPath);

        if (false === $compressedSize) {
            return CompressionResponse::failure('Failed to read compressed file size', $originalSize, $imageFormatEnum);
        }

        return CompressionResponse::success(
            path: $outputPath,
            originalSize: $originalSize,
            compressedSize: $compressedSize,
            imageFormatEnum: $imageFormatEnum,
            compressedFileName: $compressedFileName,
            outputDirectory: $outputDirectory,
        );
    }

    /**
     * Maps ImageFormatEnum to the format string expected by Imagick.
     * Imagick requires 'jpeg' instead of 'jpg'.
     */
    private function imagickFormat(ImageFormatEnum $imageFormatEnum): string
    {
        return match ($imageFormatEnum) {
            ImageFormatEnum::JPG => 'jpeg',
            ImageFormatEnum::WEBP => 'webp',
        };
    }
}
