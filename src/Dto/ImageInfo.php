<?php

namespace PicDiet\Dto;

/**
 * DTO representing the metadata of an image file.
 *
 * @author Stefano Perrini <perrini.stefano@gmail.com> aka La Matrigna
 */
class ImageInfo
{
    public function __construct(
        public readonly int $width,
        public readonly int $height,
        public readonly int $type,
        public readonly string $mime,
    ) {
    }

    /**
     * Creates an ImageInfo instance from the array returned by getimagesize().
     *
     * @param array $info The array returned by getimagesize()
     */
    public static function fromGetImageSize(array $info): self
    {
        return new self(
            width: $info[0],
            height: $info[1],
            type: $info[2],
            mime: $info['mime'],
        );
    }
}
