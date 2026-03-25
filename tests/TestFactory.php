<?php

namespace PicDiet\Tests;

use PicDiet\Enum\CompressionStrategy;
use PicDiet\Service\ImageCompressorFactory;
use PicDiet\Service\ImageCompressorGDService;
use PicDiet\Service\ImageCompressorImagickService;
use PicDiet\Service\ImageCompressorInterface;

/**
 * @author Stefano Perrini <perrini.stefano@gmail.com>
 */
class TestFactory extends AbstractTestCase
{
    // -------------------------------------------------------------------------
    // create()
    // -------------------------------------------------------------------------

    public function testCreateGdReturnsGdService(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::GD);

        $this->assertInstanceOf(ImageCompressorGDService::class, $service);
        $this->assertInstanceOf(ImageCompressorInterface::class, $service);
    }

    public function testCreateImagickReturnsImagickService(): void
    {
        if (!ImageCompressorFactory::isAvailable(CompressionStrategy::IMAGICK)) {
            $this->markTestSkipped('imagick extension not available.');
        }

        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);

        $this->assertInstanceOf(ImageCompressorImagickService::class, $service);
        $this->assertInstanceOf(ImageCompressorInterface::class, $service);
    }

    public function testCreateImagickThrowsWhenExtensionNotLoaded(): void
    {
        if (ImageCompressorFactory::isAvailable(CompressionStrategy::IMAGICK)) {
            $this->markTestSkipped('imagick extension is loaded — cannot test unavailability.');
        }

        $this->expectException(\RuntimeException::class);
        ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
    }

    public function testCreateDefaultStrategyIsGd(): void
    {
        $service = ImageCompressorFactory::factory();

        $this->assertInstanceOf(ImageCompressorGDService::class, $service);
    }

    // -------------------------------------------------------------------------
    // createBest()
    // -------------------------------------------------------------------------

    public function testCreateBestReturnsImagickWhenAvailable(): void
    {
        if (!ImageCompressorFactory::isAvailable(CompressionStrategy::IMAGICK)) {
            $this->markTestSkipped('imagick extension not available.');
        }

        $service = ImageCompressorFactory::createBest();

        $this->assertInstanceOf(ImageCompressorImagickService::class, $service);
    }

    public function testCreateBestFallsBackToGdWhenImagickNotAvailable(): void
    {
        if (ImageCompressorFactory::isAvailable(CompressionStrategy::IMAGICK)) {
            $this->markTestSkipped('imagick extension is loaded — fallback path not reachable.');
        }

        $service = ImageCompressorFactory::createBest();

        $this->assertInstanceOf(ImageCompressorGDService::class, $service);
    }

    // -------------------------------------------------------------------------
    // isAvailable()
    // -------------------------------------------------------------------------

    public function testIsAvailableGdReturnsTrueWhenLoaded(): void
    {
        $this->assertSame(extension_loaded('gd'), ImageCompressorFactory::isAvailable(CompressionStrategy::GD));
    }

    public function testIsAvailableImagickMatchesExtensionLoadedState(): void
    {
        $this->assertSame(
            extension_loaded('imagick'),
            ImageCompressorFactory::isAvailable(CompressionStrategy::IMAGICK),
        );
    }
}
