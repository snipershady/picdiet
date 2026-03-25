<?php

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
class ImageCompressorImagickService implements ImageCompressorInterface
{
    private const int MAX_WIDTH = 1920;
    private const int MAX_HEIGHT = 1080;
    private const int DEFAULT_QUALITY = 85;

    #[\Override]
    public function compress(
        string $sourcePath,
        ImageFormatEnum $format = ImageFormatEnum::WEBP,
        int $maxWidth = self::MAX_WIDTH,
        int $maxHeight = self::MAX_HEIGHT,
        ?int $quality = null,
        ?string $outputDirectory = null,
    ): CompressionResponse {
        if ($maxWidth <= 0) {
            throw new \InvalidArgumentException('maxWidth must be greater than zero.');
        }
        if ($maxHeight <= 0) {
            throw new \InvalidArgumentException('maxHeight must be greater than zero.');
        }
        if (null !== $quality && ($quality < 0 || $quality > 100)) {
            throw new \InvalidArgumentException('quality must be between 0 and 100.');
        }
        if (null !== $outputDirectory && !is_dir($outputDirectory)) {
            throw new \InvalidArgumentException('outputDirectory does not exist.');
        }
        if (null !== $outputDirectory && !is_writable($outputDirectory)) {
            throw new \InvalidArgumentException('outputDirectory is not writable.');
        }

        $quality ??= self::DEFAULT_QUALITY;

        if (!file_exists($sourcePath)) {
            return CompressionResponse::failure('Source file does not exist', $format);
        }

        $originalSize = filesize($sourcePath);

        if (false === $originalSize) {
            return CompressionResponse::failure('Failed to read source file size', $format);
        }

        try {
            $imagick = new \Imagick($sourcePath);
        } catch (\ImagickException $imagickException) {
            return CompressionResponse::failure('Invalid image file, Exception: '.$imagickException->getMessage(), $format, $originalSize);
        }

        // Strip EXIF and other metadata to reduce output size
        $imagick->stripImage();

        // Resize only if the image exceeds the max dimensions
        if ($imagick->getImageWidth() > $maxWidth || $imagick->getImageHeight() > $maxHeight) {
            $imagick->thumbnailImage($maxWidth, $maxHeight, bestfit: true);
        }

        $imagick->setImageFormat($this->imagickFormat($format));
        $imagick->setImageCompressionQuality($quality);

        $outputDirectory ??= dirname($sourcePath);
        $compressedFileName = pathinfo($sourcePath, PATHINFO_FILENAME).'_compressed.'.$format->value;
        $outputPath = $outputDirectory.'/'.$compressedFileName;

        $saveCheck = $imagick->writeImage($outputPath);
        $imagick->clear();

        if (!$saveCheck) {
            return CompressionResponse::failure('Failed to save compressed image', $format, $originalSize);
        }

        $compressedSize = filesize($outputPath);

        if (false === $compressedSize) {
            return CompressionResponse::failure('Failed to read compressed file size', $format, $originalSize);
        }

        return CompressionResponse::success(
            path: $outputPath,
            originalSize: $originalSize,
            compressedSize: $compressedSize,
            format: $format,
            compressedFileName: $compressedFileName,
            outputDirectory: $outputDirectory,
        );
    }

    /**
     * Maps ImageFormatEnum to the format string expected by Imagick.
     * Imagick requires 'jpeg' instead of 'jpg'.
     */
    private function imagickFormat(ImageFormatEnum $format): string
    {
        return match ($format) {
            ImageFormatEnum::JPG => 'jpeg',
            ImageFormatEnum::WEBP => 'webp',
        };
    }
}
