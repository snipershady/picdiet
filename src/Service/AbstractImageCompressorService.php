<?php

namespace PicDiet\Service;

/**
 * Shared base for image compressor backends.
 *
 * Centralises constants and input validation so that concrete backends
 * (GD, Imagick, …) do not repeat identical guard clauses.
 *
 * @author Stefano Perrini <perrini.stefano@gmail.com> aka La Matrigna
 */
abstract class AbstractImageCompressorService implements ImageCompressorInterface
{
    protected const int MAX_WIDTH = 1920;
    protected const int MAX_HEIGHT = 1080;
    protected const int DEFAULT_QUALITY = 85;

    /**
     * Validates the shared compress() arguments and throws
     * \InvalidArgumentException on the first violation found.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateArguments(
        int $maxWidth,
        int $maxHeight,
        ?int $quality,
        ?string $outputDirectory,
    ): void {
        if ($maxWidth <= 0) {
            throw new \InvalidArgumentException('maxWidth must be greater than zero.');
        }
        if ($maxHeight <= 0) {
            throw new \InvalidArgumentException('maxHeight must be greater than zero.');
        }
        if (null !== $quality && ($quality < 0 || $quality > 100)) {
            throw new \InvalidArgumentException('quality must be between 0 and 100.');
        }
        if (null !== $outputDirectory) {
            if (!is_dir($outputDirectory)) {
                throw new \InvalidArgumentException('outputDirectory does not exist.');
            }
            if (!is_writable($outputDirectory)) {
                throw new \InvalidArgumentException('outputDirectory is not writable.');
            }
        }
    }
}
