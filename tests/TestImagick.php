<?php

namespace PicDiet\Tests;

use PicDiet\Enum\CompressionStrategy;
use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorFactory;
use PicDiet\Service\ImageCompressorInterface;

/**
 * @author Stefano Perrini <perrini.stefano@gmail.com>
 */
class TestImagick extends AbstractTestCase
{
    private ImageCompressorInterface $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        if (!ImageCompressorFactory::isAvailable(CompressionStrategy::IMAGICK)) {
            $this->markTestSkipped('imagick extension not available.');
        }

        $this->service = ImageCompressorFactory::factory(CompressionStrategy::IMAGICK);
    }

    // -------------------------------------------------------------------------
    // Invalid arguments
    // -------------------------------------------------------------------------

    public function testCompressThrowsOnZeroMaxWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->compress('/tmp/any.jpg', maxWidth: 0);
    }

    public function testCompressThrowsOnNegativeMaxWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->compress('/tmp/any.jpg', maxWidth: -1);
    }

    public function testCompressThrowsOnZeroMaxHeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->compress('/tmp/any.jpg', maxHeight: 0);
    }

    public function testCompressThrowsOnNegativeMaxHeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->compress('/tmp/any.jpg', maxHeight: -1);
    }

    public function testCompressThrowsOnQualityBelowRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->compress('/tmp/any.jpg', quality: -1);
    }

    public function testCompressThrowsOnQualityAboveRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->compress('/tmp/any.jpg', quality: 101);
    }

    public function testCompressThrowsOnNonExistentOutputDirectory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->compress('/tmp/any.jpg', outputDirectory: '/tmp/picdiet_nonexistent_dir_'.uniqid());
    }

    // -------------------------------------------------------------------------
    // Error cases
    // -------------------------------------------------------------------------

    public function testCompressReturnsFalseWhenSourceFileDoesNotExist(): void
    {
        $response = $this->service->compress('/tmp/nonexistent_picdiet_imagick.jpg');

        $this->assertFalse($response->success);
        $this->assertSame('Source file does not exist', $response->error);
        $this->assertSame(0, $response->originalSize);
        $this->assertSame(0, $response->compressedSize);
        $this->assertNull($response->format);
    }

    public function testCompressReturnsFalseWhenFileIsNotAnImage(): void
    {
        $path = $this->createTmpTextFile();
        $response = $this->service->compress($path);

        $this->assertFalse($response->success);
        $this->assertStringStartsWith('Invalid image file', $response->error);
    }

    // -------------------------------------------------------------------------
    // Format output
    // -------------------------------------------------------------------------

    public function testCompressJpegToWebpReturnsSuccess(): void
    {
        $srcPath = $this->createTmpJpeg();
        $response = $this->service->compress($srcPath, ImageFormatEnum::WEBP);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertNull($response->error);
        $this->assertSame(ImageFormatEnum::WEBP, $response->format);
        $this->assertStringEndsWith('.webp', $response->compressedFileName);
        $this->assertFileExists($response->path);
    }

    public function testCompressJpegToJpgReturnsSuccess(): void
    {
        $srcPath = $this->createTmpJpeg();
        $response = $this->service->compress($srcPath, ImageFormatEnum::JPG);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertSame(ImageFormatEnum::JPG, $response->format);
        $this->assertStringEndsWith('.jpg', $response->compressedFileName);
        $this->assertFileExists($response->path);
    }

    public function testCompressPngToWebpReturnsSuccess(): void
    {
        $srcPath = $this->createTmpPng();
        $response = $this->service->compress($srcPath, ImageFormatEnum::WEBP);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertFileExists($response->path);
    }

    // -------------------------------------------------------------------------
    // CompressionResponse fields
    // -------------------------------------------------------------------------

    public function testCompressResponseFileNameContainsSuffix(): void
    {
        $srcPath = $this->createTmpJpeg();
        $response = $this->service->compress($srcPath, ImageFormatEnum::WEBP);
        $this->registerResponseFile($response);

        $this->assertStringContainsString('_compressed', $response->compressedFileName);
    }

    public function testCompressResponseOutputDirectoryMatchesSourceDirectory(): void
    {
        $srcPath = $this->createTmpJpeg();
        $response = $this->service->compress($srcPath, ImageFormatEnum::WEBP);
        $this->registerResponseFile($response);

        $this->assertSame(dirname($srcPath), $response->outputDirectory);
    }

    public function testCompressResponsePathIsOutputDirectoryPlusFileName(): void
    {
        $srcPath = $this->createTmpJpeg();
        $response = $this->service->compress($srcPath, ImageFormatEnum::WEBP);
        $this->registerResponseFile($response);

        $this->assertSame(
            $response->outputDirectory.'/'.$response->compressedFileName,
            $response->path,
        );
    }

    public function testCompressResponseOriginalSizeIsPositive(): void
    {
        $srcPath = $this->createTmpJpeg();
        $response = $this->service->compress($srcPath);
        $this->registerResponseFile($response);

        $this->assertGreaterThan(0, $response->originalSize);
        $this->assertGreaterThan(0, $response->compressedSize);
    }

    // -------------------------------------------------------------------------
    // Output directory
    // -------------------------------------------------------------------------

    public function testCompressWritesToCustomOutputDirectory(): void
    {
        $srcPath = $this->createTmpJpeg();
        $customDir = sys_get_temp_dir().'/picdiet_out_'.uniqid();
        mkdir($customDir);

        $response = $this->service->compress($srcPath, outputDirectory: $customDir);

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
        $srcPath = $this->createTmpJpeg(width: 50, height: 50);
        $response = $this->service->compress($srcPath, ImageFormatEnum::JPG, 200, 200);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $info = getimagesize($response->path);
        $this->assertSame(50, $info[0]);
        $this->assertSame(50, $info[1]);
    }

    public function testCompressResizesImageLargerThanMaxDimensions(): void
    {
        $srcPath = $this->createTmpJpeg(width: 400, height: 300);
        $response = $this->service->compress($srcPath, ImageFormatEnum::JPG, 100, 100);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);

        $info = getimagesize($response->path);
        $this->assertLessThanOrEqual(100, $info[0]);
        $this->assertLessThanOrEqual(100, $info[1]);
    }

    public function testCompressResizeMaintainsAspectRatio(): void
    {
        $srcPath = $this->createTmpJpeg(width: 400, height: 200);
        $response = $this->service->compress($srcPath, ImageFormatEnum::JPG, 100, 100);
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
        $srcPath = $this->createTmpJpeg(width: 800, height: 600);
        $response = $this->service->compress($srcPath, ImageFormatEnum::WEBP);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertLessThanOrEqual($response->originalSize, $response->compressedSize);
    }
}
