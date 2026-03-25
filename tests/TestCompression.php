<?php

namespace PicDiet\Tests;

use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorGDService;

/**
 * @author Stefano Perrini <perrini.stefano@gmail.com>
 */
class TestCompression extends AbstractTestCase
{
    private ImageCompressorGDService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ImageCompressorGDService();
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

    public function testCompressWritesToCustomOutputDirectory(): void
    {
        $srcPath = $this->createTmpJpeg();
        $customDir = sys_get_temp_dir().'/picdiet_out_'.uniqid();
        mkdir($customDir);

        $response = $this->service->compress($srcPath, outputDirectory: $customDir);

        // Clean up output file then directory manually
        if (null !== $response->path && file_exists($response->path)) {
            unlink($response->path);
        }
        rmdir($customDir);

        $this->assertTrue($response->success);
        $this->assertSame($customDir, $response->outputDirectory);
        $this->assertStringStartsWith($customDir, $response->path);
    }

    // -------------------------------------------------------------------------
    // Error cases
    // -------------------------------------------------------------------------

    public function testCompressReturnsFalseWhenSourceFileDoesNotExist(): void
    {
        $response = $this->service->compress('/tmp/nonexistent_picdiet_file.jpg');

        $this->assertFalse($response->success);
        $this->assertSame('Source file does not exist', $response->error);
        $this->assertNull($response->path);
        $this->assertSame(0, $response->originalSize);
        $this->assertSame(0, $response->compressedSize);
        $this->assertNull($response->compressedFileName);
        $this->assertNull($response->outputDirectory);
    }

    public function testCompressReturnsFalseWhenFileIsNotAnImage(): void
    {
        $path = $this->createTmpTextFile();
        $response = $this->service->compress($path);

        $this->assertFalse($response->success);
        $this->assertSame('Invalid image file', $response->error);
        $this->assertNull($response->path);
        $this->assertNull($response->compressedFileName);
        $this->assertNull($response->outputDirectory);
    }

    // -------------------------------------------------------------------------
    // Format output
    // -------------------------------------------------------------------------

    public function testCompressWebpToWebpReturnsSuccess(): void
    {
        $srcPath = $this->createTmpWebp();
        $response = $this->service->compress($srcPath, ImageFormatEnum::WEBP);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertNull($response->error);
        $this->assertSame(ImageFormatEnum::WEBP, $response->format);
        $this->assertFileExists($response->path);
    }

    public function testCompressWebpToJpgReturnsSuccess(): void
    {
        $srcPath = $this->createTmpWebp();
        $response = $this->service->compress($srcPath, ImageFormatEnum::JPG);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertNull($response->error);
        $this->assertSame(ImageFormatEnum::JPG, $response->format);
        $this->assertFileExists($response->path);
    }

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
        $this->assertNull($response->error);
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
        $this->assertSame(ImageFormatEnum::WEBP, $response->format);
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

    public function testCompressSuccessResponseCompressedSizeIsLessOrEqualToOriginal(): void
    {
        $srcPath = $this->createTmpJpeg(width: 800, height: 600);
        $response = $this->service->compress($srcPath, ImageFormatEnum::WEBP);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertLessThanOrEqual($response->originalSize, $response->compressedSize);
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
        // 400x200 → max 100x100: ratio limited by width → 100x50
        $srcPath = $this->createTmpJpeg(width: 400, height: 200);
        $response = $this->service->compress($srcPath, ImageFormatEnum::JPG, 100, 100);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);

        $info = getimagesize($response->path);
        $this->assertSame(100, $info[0]);
        $this->assertSame(50, $info[1]);
    }

    // -------------------------------------------------------------------------
    // Custom quality
    // -------------------------------------------------------------------------

    public function testCompressAcceptsBoundaryQualityZero(): void
    {
        $srcPath = $this->createTmpJpeg();
        $response = $this->service->compress($srcPath, quality: 0);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertGreaterThan(0, $response->compressedSize);
    }

    public function testCompressAcceptsBoundaryQualityOneHundred(): void
    {
        $srcPath = $this->createTmpJpeg();
        $response = $this->service->compress($srcPath, quality: 100);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertGreaterThan(0, $response->compressedSize);
    }

    public function testCompressCustomQualityProducesOutput(): void
    {
        $srcPath = $this->createTmpJpeg();
        $response = $this->service->compress($srcPath, ImageFormatEnum::JPG, quality: 50);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertGreaterThan(0, $response->compressedSize);
    }

    // -------------------------------------------------------------------------
    // PNG transparency
    // -------------------------------------------------------------------------

    public function testCompressPngPreservesTransparencyInWebpOutput(): void
    {
        $srcPath = $this->createTmpPng();
        $response = $this->service->compress($srcPath, ImageFormatEnum::WEBP);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);

        $outputImage = imagecreatefromwebp($response->path);
        $this->assertNotFalse($outputImage, 'Failed to load compressed WebP output.');

        // Source PNG is fully transparent — alpha in GD ranges 0 (opaque) to 127 (transparent)
        $rgba = imagecolorsforindex($outputImage, imagecolorat($outputImage, 0, 0));
        imagedestroy($outputImage);

        $this->assertGreaterThan(0, $rgba['alpha'], 'PNG transparency was not preserved in WebP output.');
    }

    // -------------------------------------------------------------------------
    // Integration
    // -------------------------------------------------------------------------

    public function testCompressLocalPngFixtureProducesSmalllerOutput(): void
    {
        $srcPath = __DIR__.'/fixtures/sample.png';
        $this->assertFileExists($srcPath, 'PNG fixture is missing from tests/fixtures/');

        $response = $this->service->compress($srcPath);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertGreaterThan($response->compressedSize, $response->originalSize);
    }
}
