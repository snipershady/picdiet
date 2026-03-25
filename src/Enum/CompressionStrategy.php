<?php

namespace PicDiet\Enum;

/**
 * Available backend strategies for image compression.
 *
 * Use {@see \PicDiet\Service\ImageCompressorFactory} to obtain the
 * corresponding service implementation.
 *
 * @author Stefano Perrini <perrini.stefano@gmail.com> aka La Matrigna
 */
enum CompressionStrategy
{
    /** PHP GD extension — widely available, lower memory overhead. */
    case GD;

    /** PHP Imagick extension — higher output quality, supports AVIF, HEIC, animated GIF. */
    case IMAGICK;
}
