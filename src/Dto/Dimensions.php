<?php

namespace PicDiet\Dto;

/**
 * DTO representing the width and height of an image (in pixels).
 *
 * @author Stefano Perrini <perrini.stefano@gmail.com> aka La Matrigna
 */
class Dimensions
{
    public function __construct(
        public readonly int $width,
        public readonly int $height,
    ) {
        if ($this->width <= 0) {
            throw new \InvalidArgumentException('Width must be greater than zero.');
        }
        if ($this->height <= 0) {
            throw new \InvalidArgumentException('Height must be greater than zero.');
        }
    }
}
