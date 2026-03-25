<?php

namespace PicDiet\Tests;

use PicDiet\Enum\CompressionStrategy;
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorFactory;

/**
 * @author Stefano Perrini <perrini.stefano@gmail.com>
 */
class TestImagick extends AbstractTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        if (!ImageCompressorFactory::isAvailable(CompressionStrategy::IMAGICK)) {
            $this->markTestSkipped('imagick extension not available.');
        }
    }

    // -------------------------------------------------------------------------
    // Invalid arguments
    // -------------------------------------------------------------------------

    public function testCompressThrowsOnZeroMaxWidth(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $this->expectException(\InvalidArgumentException::class);
        $service->compress('/tmp/any.jpg', maxWidth: 0);
    }

    public function testCompressThrowsOnNegativeMaxWidth(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $this->expectException(\InvalidArgumentException::class);
        $service->compress('/tmp/any.jpg', maxWidth: -1);
    }

    public function testCompressThrowsOnZeroMaxHeight(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $this->expectException(\InvalidArgumentException::class);
        $service->compress('/tmp/any.jpg', maxHeight: 0);
    }

    public function testCompressThrowsOnNegativeMaxHeight(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $this->expectException(\InvalidArgumentException::class);
        $service->compress('/tmp/any.jpg', maxHeight: -1);
    }

    public function testCompressThrowsOnQualityBelowRange(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $this->expectException(\InvalidArgumentException::class);
        $service->compress('/tmp/any.jpg', quality: -1);
    }

    public function testCompressThrowsOnQualityAboveRange(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $this->expectException(\InvalidArgumentException::class);
        $service->compress('/tmp/any.jpg', quality: 101);
    }

    public function testCompressThrowsOnNonExistentOutputDirectory(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $this->expectException(\InvalidArgumentException::class);
        $service->compress('/tmp/any.jpg', outputDirectory: '/tmp/picdiet_nonexistent_dir_'.uniqid());
    }

    // -------------------------------------------------------------------------
    // Error cases
    // -------------------------------------------------------------------------

    public function testCompressReturnsFalseWhenSourceFileDoesNotExist(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $response = $service->compress('/tmp/nonexistent_picdiet_imagick.jpg');

        $this->assertFalse($response->success);
        $this->assertSame('Source file does not exist', $response->error);
        $this->assertSame(0, $response->originalSize);
        $this->assertSame(0, $response->compressedSize);
    }

    public function testCompressReturnsFalseWhenFileIsNotAnImage(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $path = $this->createTmpTextFile();
        $response = $service->compress($path);

        $this->assertFalse($response->success);
        $this->assertStringStartsWith('Invalid image file', $response->error);
    }

    // -------------------------------------------------------------------------
    // Format output
    // -------------------------------------------------------------------------

    public function testCompressJpegToWebpReturnsSuccess(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $srcPath = $this->createTmpJpeg();
        $response = $service->compress($srcPath, ImageFormatEnum::WEBP);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertNull($response->error);
        $this->assertSame(ImageFormatEnum::WEBP, $response->format);
        $this->assertStringEndsWith('.webp', $response->compressedFileName);
        $this->assertFileExists($response->path);
    }

    public function testCompressJpegToJpgReturnsSuccess(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $srcPath = $this->createTmpJpeg();
        $response = $service->compress($srcPath, ImageFormatEnum::JPG);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertSame(ImageFormatEnum::JPG, $response->format);
        $this->assertStringEndsWith('.jpg', $response->compressedFileName);
        $this->assertFileExists($response->path);
    }

    public function testCompressPngToWebpReturnsSuccess(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $srcPath = $this->createTmpPng();
        $response = $service->compress($srcPath, ImageFormatEnum::WEBP);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertFileExists($response->path);
    }

    // -------------------------------------------------------------------------
    // Output directory
    // -------------------------------------------------------------------------

    public function testCompressWritesToCustomOutputDirectory(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $srcPath = $this->createTmpJpeg();
        $customDir = sys_get_temp_dir().'/picdiet_out_'.uniqid();
        mkdir($customDir);

        $response = $service->compress($srcPath, outputDirectory: $customDir);

        if (null !== $response->path && file_exists($response->path)) {
            unlink($response->path);
        }
        rmdir($customDir);

        $this->assertTrue($response->success);
        $this->assertSame($customDir, $response->outputDirectory);
        $this->assertStringStartsWith($customDir, $response->path);
    }

    // -------------------------------------------------------------------------
    // Resize behaviour
    // -------------------------------------------------------------------------

    public function testCompressDoesNotUpscaleImageSmallerThanMaxDimensions(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $srcPath = $this->createTmpJpeg(width: 50, height: 50);
        $response = $service->compress($srcPath, ImageFormatEnum::JPG, 200, 200);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $info = getimagesize($response->path);
        $this->assertSame(50, $info[0]);
        $this->assertSame(50, $info[1]);
    }

    public function testCompressResizeMaintainsAspectRatio(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $srcPath = $this->createTmpJpeg(width: 400, height: 200);
        $response = $service->compress($srcPath, ImageFormatEnum::JPG, 100, 100);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $info = getimagesize($response->path);
        $this->assertSame(100, $info[0]);
        $this->assertSame(50, $info[1]);
    }

    // -------------------------------------------------------------------------
    // Sizes
    // -------------------------------------------------------------------------

    public function testCompressSuccessResponseCompressedSizeIsLessOrEqualToOriginal(): void
    {
        $service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
        $srcPath = $this->createTmpJpeg(width: 800, height: 600);
        $response = $service->compress($srcPath, ImageFormatEnum::WEBP);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertLessThanOrEqual($response->originalSize, $response->compressedSize);
    }
}
