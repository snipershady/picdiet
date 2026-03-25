<?php

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
    /**
     * Compresses an image and converts it to WebP or JPEG format.
     *
     * Supported input formats: JPEG, PNG, GIF, WebP.
     * Note: animated GIFs are accepted but only the first frame is processed;
     * the animation is not preserved in the output.
     *
     * @param string          $sourcePath Path to the source image
     * @param ImageFormatEnum $format     Output format (default: ImageFormatEnum::WEBP)
     * @param int             $maxWidth   Maximum width (default: 1920)
     * @param int             $maxHeight  Maximum height (default: 1080)
     * @param int|null        $quality    Compression quality 0-100 (default: 85)
     */
    #[\Override]
    public function compress(
        string $sourcePath,
        ImageFormatEnum $format = ImageFormatEnum::WEBP,
        int $maxWidth = self::MAX_WIDTH,
        int $maxHeight = self::MAX_HEIGHT,
        ?int $quality = null,
        ?string $outputDirectory = null,
    ): CompressionResponse {
        $this->validateArguments($maxWidth, $maxHeight, $quality, $outputDirectory);

        $quality ??= self::DEFAULT_QUALITY;

        if (!file_exists($sourcePath)) {
            return CompressionResponse::failure('Source file does not exist', $format);
        }

        $originalSize = filesize($sourcePath);

        if (false === $originalSize) {
            return CompressionResponse::failure('Failed to read source file size', $format);
        }

        $rawImageInfo = getimagesize($sourcePath);

        if (false === $rawImageInfo) {
            return CompressionResponse::failure('Invalid image file', $format, $originalSize);
        }

        $imageInfo = ImageInfo::fromGetImageSize($rawImageInfo);

        // Create image resource from source
        $sourceImage = $this->createImageFromFile($sourcePath, $imageInfo->type);

        if (false === $sourceImage) {
            return CompressionResponse::failure('Failed to create image resource', $format, $originalSize);
        }

        return $this->processImage(
            sourcePath: $sourcePath,
            sourceImage: $sourceImage,
            imageInfo: $imageInfo,
            originalSize: $originalSize,
            format: $format,
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
        \GdImage $sourceImage,
        ImageInfo $imageInfo,
        int $originalSize,
        ImageFormatEnum $format,
        int $maxWidth,
        int $maxHeight,
        int $quality,
        string $outputDirectory,
    ): CompressionResponse {
        $newDimensions = $this->calculateDimensions($imageInfo, $maxWidth, $maxHeight);

        $resizedImage = imagecreatetruecolor($newDimensions->width, $newDimensions->height);

        // Preserve transparency for PNG
        if (IMAGETYPE_PNG === $imageInfo->type) {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefilledrectangle($resizedImage, 0, 0, $newDimensions->width, $newDimensions->height, $transparent);
        }

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

        $compressedFileName = pathinfo($sourcePath, PATHINFO_FILENAME).'_compressed.'.$format->value;
        $outputPath = $outputDirectory.'/'.$compressedFileName;

        $saveCheck = $this->saveImage($resizedImage, $outputPath, $format, $quality);

        imagedestroy($sourceImage);
        imagedestroy($resizedImage);

        if (false === $saveCheck) {
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
