<?php

namespace PicDiet\Enum;

/**
 * Supported output formats for image compression.
 *
 * @author Stefano Perrini <perrini.stefano@gmail.com> aka La Matrigna
 */
enum ImageFormatEnum: string
{
    /** WebP format — superior compression with transparency support. Value: 'webp'. */
    case WEBP = 'webp';

    /** JPEG format — universal compatibility, no transparency. Value: 'jpg'. */
    case JPG = 'jpg';
}
