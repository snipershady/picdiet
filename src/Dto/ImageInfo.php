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
        if ($this->width <= 0) {
            throw new \InvalidArgumentException('Width must be greater than zero.');
        }
        if ($this->height <= 0) {
            throw new \InvalidArgumentException('Height must be greater than zero.');
        }
        if ($this->type <= 0) {
            throw new \InvalidArgumentException('Type must be a valid IMAGETYPE_* constant.');
        }
        if ('' === trim($this->mime)) {
            throw new \InvalidArgumentException('MIME type must not be empty.');
        }
        if (!str_starts_with($this->mime, 'image/')) {
            throw new \InvalidArgumentException("MIME type invalid, must be of type: 'image/...'.");
        }
    }

    /**
     * Creates an ImageInfo instance from the array returned by getimagesize().
     *
     * @param array{0: int, 1: int, 2: int, mime: string} $info The array returned by getimagesize()
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
