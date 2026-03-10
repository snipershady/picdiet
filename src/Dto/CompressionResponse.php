<?php

namespace PicDiet\Dto;

/**
 * DTO representing the result of an image compression operation.
 *
 * @author Stefano Perrini <perrini.stefano@gmail.com> aka La Matrigna
 */
class CompressionResponse
{

    public function __construct(
            public readonly bool $success,
            public readonly ?string $path,
            public readonly ?string $error,
            public readonly int $originalSize,
            public readonly int $compressedSize,
            public readonly string $format)
    {
        
    }
}
