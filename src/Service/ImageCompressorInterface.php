<?php

namespace PicDiet\Service;

use PicDiet\Dto\CompressionResponse;
use PicDiet\Enum\ImageFormatEnum;

/**
 * Contract for image compression services.
 *
 * Implement this interface to provide alternative backends
 * (e.g. Imagick, cloud APIs) without changing calling code.
 *
 * @author Stefano Perrini <perrini.stefano@gmail.com> aka La Matrigna
 */
interface ImageCompressorInterface
{
    /**
     * Compresses an image and converts it to WebP or JPEG format.
     *
     * Supported input formats: JPEG, PNG, GIF, WebP.
     * Note: animated GIFs are accepted but only the first frame is processed;
     * the animation is not preserved in the output.
     *
     * @param string          $sourcePath      Path to the source image
     * @param ImageFormatEnum $format          Output format (default: ImageFormatEnum::WEBP)
     * @param int             $maxWidth        Maximum width (default: 1920)
     * @param int             $maxHeight       Maximum height (default: 1080)
     * @param int|null        $quality         Compression quality 0-100 (default: 85)
     * @param string|null     $outputDirectory Directory for the compressed file (default: same as source)
     *
     * @throws \InvalidArgumentException if maxWidth, maxHeight or quality are out of range,
     *                                   or if outputDirectory does not exist or is not writable
     */
    public function compress(
        string $sourcePath,
        ImageFormatEnum $format = ImageFormatEnum::WEBP,
        int $maxWidth = 1920,
        int $maxHeight = 1080,
        ?int $quality = null,
        ?string $outputDirectory = null,
    ): CompressionResponse;
}
