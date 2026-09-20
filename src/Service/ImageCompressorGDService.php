<?php

declare(strict_types=1);

namespace PicDiet\Service;

use PicDiet\Dto\CompressionResponse;
use PicDiet\Dto\Dimensions;
use PicDiet\Dto\ImageInfo;
use PicDiet\Enum\ImageFormatEnum;

/**
 * Service for compressing and converting images to WebP or JPEG format.
 *
 * @author Stefano Perrini <perrini.stefano@gmail.com> aka La Matrigna
 */
class ImageCompressorGDService extends AbstractImageCompressorService
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

        $rawImageInfo = getimagesize($sourcePath);

        if (false === $rawImageInfo) {
            return CompressionResponse::failure('Invalid image file', $originalSize, $imageFormatEnum);
        }

        $imageInfo = ImageInfo::fromGetImageSize($rawImageInfo);

        // Create image resource from source
        $sourceImage = $this->createImageFromFile($sourcePath, $imageInfo->type);

        if (false === $sourceImage) {
            return CompressionResponse::failure('Failed to create image resource', $originalSize, $imageFormatEnum);
        }

        return $this->processImage(
            sourcePath: $sourcePath,
            gdImage: $sourceImage,
            imageInfo: $imageInfo,
            originalSize: $originalSize,
            imageFormatEnum: $imageFormatEnum,
            maxWidth: $maxWidth,
            maxHeight: $maxHeight,
            quality: $quality,
            outputDirectory: $outputDirectory ?? dirname($sourcePath),
        );
    }

    /**
     * Executes the full image transformation pipeline: resize, transparency
     * handling, resampling, saving, and size verification.
     */
    private function processImage(
        string $sourcePath,
        \GdImage $gdImage,
        ImageInfo $imageInfo,
        int $originalSize,
        ImageFormatEnum $imageFormatEnum,
        int $maxWidth,
        int $maxHeight,
        int $quality,
        string $outputDirectory,
    ): CompressionResponse {
        $newDimensions = $this->calculateDimensions($imageInfo, $maxWidth, $maxHeight);
        $width = $newDimensions->width;
        $height = $newDimensions->height;

        if ($width < 1 || $height < 1) {
            return CompressionResponse::failure('Computed dimensions must be positive', $originalSize, $imageFormatEnum);
        }

        $resizedImage = imagecreatetruecolor($width, $height);

        // Preserve transparency for PNG
        if (IMAGETYPE_PNG === $imageInfo->type) {
            imagealphablending($resizedImage, enable: false);
            imagesavealpha($resizedImage, enable: true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);

            if (false === $transparent) {
                imagedestroy($gdImage);
                imagedestroy($resizedImage);

                return CompressionResponse::failure('Failed to allocate transparent color', $originalSize, $imageFormatEnum);
            }

            imagefilledrectangle($resizedImage, 0, 0, $width, $height, $transparent);
        }

        imagecopyresampled(
            $resizedImage,
            $gdImage,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $imageInfo->width,
            $imageInfo->height
        );

        $compressedFileName = pathinfo($sourcePath, PATHINFO_FILENAME) . '_compressed.' . $imageFormatEnum->value;
        $outputPath = $outputDirectory . '/' . $compressedFileName;

        $saveCheck = $this->saveImage($resizedImage, $outputPath, $imageFormatEnum, $quality);

        imagedestroy($gdImage);
        imagedestroy($resizedImage);

        if (false === $saveCheck) {
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
     *
     * @return bool true on success, false on failure
     */
    private function saveImage(
        \GdImage $gdImage,
        string $outputPath,
        ImageFormatEnum $imageFormatEnum,
        int $quality,
    ): bool {
        return match ($imageFormatEnum) {
            ImageFormatEnum::WEBP => imagewebp($gdImage, $outputPath, $quality),
            ImageFormatEnum::JPG => imagejpeg($gdImage, $outputPath, $quality),
        };
    }

    /**
     * Calculates new dimensions maintaining aspect ratio.
     *
     * @param ImageInfo $imageInfo Source image information
     * @param int       $maxWidth  Maximum width
     * @param int       $maxHeight Maximum height
     */
    private function calculateDimensions(
        ImageInfo $imageInfo,
        int $maxWidth,
        int $maxHeight,
    ): Dimensions {
        // If image is smaller than max dimensions, keep original size
        if ($imageInfo->width <= $maxWidth && $imageInfo->height <= $maxHeight) {
            return new Dimensions($imageInfo->width, $imageInfo->height);
        }

        $ratio = min($maxWidth / $imageInfo->width, $maxHeight / $imageInfo->height);

        return new Dimensions(
            width: (int) round($imageInfo->width * $ratio),
            height: (int) round($imageInfo->height * $ratio),
        );
    }
}
