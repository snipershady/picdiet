<?php

namespace PicDiet\Enum;

/**
 * Supported output formats for image compression.
 *
 * @author Stefano Perrini <perrini.stefano@gmail.com> aka La Matrigna
 */
enum ImageFormatEnum: string
{
    case WEBP = 'webp';
    case JPG = 'jpg';
}
