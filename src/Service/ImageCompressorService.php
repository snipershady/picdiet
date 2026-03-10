<?php

namespace PicDiet\Service;

use PicDiet\Dto\CompressionResponse;

/**
 * Service for compressing and converting images to WebP or JPEG format.
 *
 * @author Stefano Perrini <perrini.stefano@gmail.com> aka La Matrigna
 */
class ImageCompressorService
{
    private const int MAX_WIDTH = 1920;
    private const int MAX_HEIGHT = 1080;
    private const int WEBP_QUALITY = 85;
    private const int JPEG_QUALITY = 85;

    /**
     * Compresses an image and converts it to WebP or JPEG format.
     *
     * @param string $sourcePath Path to the source image
     * @param string $format     Output format: 'webp' or 'jpeg' (default: 'webp')
     * @param int    $maxWidth   Maximum width (default: 1920)
     * @param int    $maxHeight  Maximum height (default: 1080)
     * @param int    $quality    Compression quality 0-100 (default: 85)
     */
    public function compress(
        string $sourcePath,
        string $format = 'webp',
        int $maxWidth = self::MAX_WIDTH,
        int $maxHeight = self::MAX_HEIGHT,
        ?int $quality = null,
    ): CompressionResponse {
        if (!file_exists($sourcePath)) {
            return new CompressionResponse(
                success: false,
                path: null,
                error: 'Source file does not exist',
                originalSize: 0,
                compressedSize: 0,
                format: $format,
            );
        }

        $originalSize = filesize($sourcePath);
        $imageInfo = getimagesize($sourcePath);

        if (false === $imageInfo) {
            return new CompressionResponse(
                success: false,
                path: null,
                error: 'Invalid image file',
                originalSize: $originalSize,
                compressedSize: 0,
                format: $format,
            );
        }

        // Set default quality based on format
        if (null === $quality) {
            $quality = 'webp' === $format ? self::WEBP_QUALITY : self::JPEG_QUALITY;
        }

        // Create image resource from source
        $sourceImage = $this->createImageFromFile($sourcePath, $imageInfo[2]);

        if (false === $sourceImage) {
            return new CompressionResponse(
                success: false,
                path: null,
                error: 'Failed to create image resource',
                originalSize: $originalSize,
                compressedSize: 0,
                format: $format,
            );
        }

        // Calculate new dimensions maintaining aspect ratio
        [$newWidth, $newHeight] = $this->calculateDimensions(
            $imageInfo[0],
            $imageInfo[1],
            $maxWidth,
            $maxHeight
        );

        // Create new image with calculated dimensions
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG
        if (IMAGETYPE_PNG === $imageInfo[2]) {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Resize the image
        imagecopyresampled(
            $resizedImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $newWidth,
            $newHeight,
            $imageInfo[0],
            $imageInfo[1]
        );

        // Generate output path
        $pathInfo = pathinfo($sourcePath);
        $extension = 'webp' === $format ? 'webp' : 'jpg';
        $outputPath = $pathInfo['dirname'].'/'.$pathInfo['filename'].'_compressed.'.$extension;

        // Save compressed image
        $success = false;
        if ('webp' === $format && function_exists('imagewebp')) {
            $success = imagewebp($resizedImage, $outputPath, $quality);
        } elseif ('jpeg' === $format || 'jpg' === $format) {
            $success = imagejpeg($resizedImage, $outputPath, $quality);
        } else {
            // Fallback to JPEG if WebP is not supported
            $success = imagejpeg($resizedImage, $outputPath, $quality);
            $format = 'jpeg';
        }

        // Free memory
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);

        if (!$success) {
            return new CompressionResponse(
                success: false,
                path: null,
                error: 'Failed to save compressed image',
                originalSize: $originalSize,
                compressedSize: 0,
                format: $format,
            );
        }

        $compressedSize = filesize($outputPath);

        return new CompressionResponse(
            success: true,
            path: $outputPath,
            error: null,
            originalSize: $originalSize,
            compressedSize: $compressedSize,
            format: $format,
        );
    }

    /**
     * Creates an image resource from a file based on its type.
     *
     * @param string $path Path to the image file
     * @param int    $type Image type constant (IMAGETYPE_*)
     */
    private function createImageFromFile(string $path, int $type): \GdImage|false
    {
        return match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_GIF => imagecreatefromgif($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default => false,
        };
    }

    /**
     * Calculates new dimensions maintaining aspect ratio.
     *
     * @param int $originalWidth  Original image width
     * @param int $originalHeight Original image height
     * @param int $maxWidth       Maximum width
     * @param int $maxHeight      Maximum height
     *
     * @return array{0: int, 1: int} New width and height
     */
    private function calculateDimensions(
        int $originalWidth,
        int $originalHeight,
        int $maxWidth,
        int $maxHeight,
    ): array {
        // If image is smaller than max dimensions, keep original size
        if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
            return [$originalWidth, $originalHeight];
        }

        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);

        return [
            (int) round($originalWidth * $ratio),
            (int) round($originalHeight * $ratio),
        ];
    }
}
