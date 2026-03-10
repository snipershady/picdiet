<?php

namespace PicDiet\Service;

use PicDiet\Dto\CompressionResponse;
use PicDiet\Dto\ImageInfo;
use PicDiet\Enum\ImageFormatEnum;

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
     * @param string          $sourcePath Path to the source image
     * @param ImageFormatEnum $format     Output format (default: ImageFormatEnum::WEBP)
     * @param int             $maxWidth   Maximum width (default: 1920)
     * @param int             $maxHeight  Maximum height (default: 1080)
     * @param int             $quality    Compression quality 0-100 (default: 85)
     */
    public function compress(
        string $sourcePath,
        ImageFormatEnum $format = ImageFormatEnum::WEBP,
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
                compressedFileName: null,
                outputDirectory: null,
            );
        }

        $originalSize = filesize($sourcePath);
        $rawImageInfo = getimagesize($sourcePath);

        if (false === $rawImageInfo) {
            return new CompressionResponse(
                success: false,
                path: null,
                error: 'Invalid image file',
                originalSize: $originalSize,
                compressedSize: 0,
                format: $format,
                compressedFileName: null,
                outputDirectory: null,
            );
        }

        $imageInfo = ImageInfo::fromGetImageSize($rawImageInfo);

        // Set default quality based on format
        if (null === $quality) {
            $quality = ImageFormatEnum::WEBP === $format ? self::WEBP_QUALITY : self::JPEG_QUALITY;
        }

        // Create image resource from source
        $sourceImage = $this->createImageFromFile($sourcePath, $imageInfo->type);

        if (false === $sourceImage) {
            return new CompressionResponse(
                success: false,
                path: null,
                error: 'Failed to create image resource',
                originalSize: $originalSize,
                compressedSize: 0,
                format: $format,
                compressedFileName: null,
                outputDirectory: null,
            );
        }

        // Calculate new dimensions maintaining aspect ratio
        $newDimensions = $this->calculateDimensions($imageInfo, $maxWidth, $maxHeight);

        // Create new image with calculated dimensions
        $resizedImage = imagecreatetruecolor($newDimensions->width, $newDimensions->height);

        // Preserve transparency for PNG
        if (IMAGETYPE_PNG === $imageInfo->type) {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefilledrectangle($resizedImage, 0, 0, $newDimensions->width, $newDimensions->height, $transparent);
        }

        // Resize the image
        imagecopyresampled(
            $resizedImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $newDimensions->width,
            $newDimensions->height,
            $imageInfo->width,
            $imageInfo->height
        );

        // Generate output path
        $pathInfo = pathinfo($sourcePath);
        $compressedFileName = $pathInfo['filename'].'_compressed.'.$format->value;
        $outputDirectory = $pathInfo['dirname'];
        $outputPath = $outputDirectory.'/'.$compressedFileName;

        // Save compressed image
        $saveCheck = $this->saveImage($resizedImage, $outputPath, $format, $quality);

        // Free memory
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);

        if (false === $saveCheck) {
            return new CompressionResponse(
                success: false,
                path: null,
                error: 'Failed to save compressed image',
                originalSize: $originalSize,
                compressedSize: 0,
                format: $format,
                compressedFileName: null,
                outputDirectory: null,
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
            compressedFileName: $compressedFileName,
            outputDirectory: $outputDirectory,
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
     * Saves the resized image to disk in the specified format.
     * Falls back to JPEG if WebP is requested but not supported.
     *
     * @return bool true for format selected or false on failure
     */
    private function saveImage(
        \GdImage $image,
        string $outputPath,
        ImageFormatEnum $format,
        int $quality,
    ): bool {
        return match ($format) {
            ImageFormatEnum::WEBP => imagewebp($image, $outputPath, $quality),
            ImageFormatEnum::JPG => imagejpeg($image, $outputPath, $quality),
        };
    }

    /**
     * Calculates new dimensions maintaining aspect ratio.
     *
     * @param ImageInfo $imageInfo Source image information
     * @param int       $maxWidth  Maximum width
     * @param int       $maxHeight Maximum height
     *
     * @return ImageInfo New dimensions with the same type and mime of the source
     */
    private function calculateDimensions(
        ImageInfo $imageInfo,
        int $maxWidth,
        int $maxHeight,
    ): ImageInfo {
        // If image is smaller than max dimensions, keep original size
        if ($imageInfo->width <= $maxWidth && $imageInfo->height <= $maxHeight) {
            return $imageInfo;
        }

        $ratio = min($maxWidth / $imageInfo->width, $maxHeight / $imageInfo->height);

        return new ImageInfo(
            width: (int) round($imageInfo->width * $ratio),
            height: (int) round($imageInfo->height * $ratio),
            type: $imageInfo->type,
            mime: $imageInfo->mime,
        );
    }
}
