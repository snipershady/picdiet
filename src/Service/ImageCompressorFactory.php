<?php

namespace PicDiet\Service;

use PicDiet\Enum\CompressionStrategy;

/**
 * Factory for creating image compressor instances.
 *
 * Usage:
 *   $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
 *   $best    = ImageCompressorFactory::createBest();
 *
 * @author Stefano Perrini <perrini.stefano@gmail.com> aka La Matrigna
 */
class ImageCompressorFactory
{
    /**
     * Creates a compressor for the given strategy.
     *
     * @throws \RuntimeException if the required PHP extension is not loaded
     */
    public static function factory(CompressionStrategy $strategy = CompressionStrategy::GD): ImageCompressorInterface
    {
        return match ($strategy) {
            CompressionStrategy::GD => self::createGd(),
            CompressionStrategy::IMAGICK => self::createImagick(),
        };
    }

    /**
     * Creates the best available compressor: Imagick if the extension is
     * loaded, GD otherwise.
     */
    public static function createBest(): ImageCompressorInterface
    {
        return self::isAvailable(CompressionStrategy::IMAGICK)
            ? new ImageCompressorImagickService()
            : new ImageCompressorGDService();
    }

    /**
     * Returns true if the PHP extension required by the given strategy is loaded.
     */
    public static function isAvailable(CompressionStrategy $strategy): bool
    {
        return match ($strategy) {
            CompressionStrategy::GD => extension_loaded('gd'),
            CompressionStrategy::IMAGICK => extension_loaded('imagick'),
        };
    }

    private static function createGd(): ImageCompressorGDService
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('The gd PHP extension is not loaded. Install it or use CompressionStrategy::IMAGICK instead.');
        }

        return new ImageCompressorGDService();
    }

    private static function createImagick(): ImageCompressorImagickService
    {
        if (!extension_loaded('imagick')) {
            throw new \RuntimeException('The imagick PHP extension is not loaded. Install it or use CompressionStrategy::GD instead.');
        }

        return new ImageCompressorImagickService();
    }
}
