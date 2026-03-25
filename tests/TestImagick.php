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
    protected function setUp(): void
    {
        parent::setUp();

        if (!ImageCompressorFactory::isAvailable(CompressionStrategy::IMAGICK)) {
            $this->markTestSkipped('imagick extension not available.');
        }
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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createTmpJpeg(int $width = 200, int $height = 150): string
    {
        $path = sys_get_temp_dir().'/picdiet_imagick_test_'.uniqid().'.jpg';
        $img = imagecreatetruecolor($width, $height);
        imagefilledrectangle($img, 0, 0, $width, $height, imagecolorallocate($img, 255, 128, 0));
        imagejpeg($img, $path, 90);
        imagedestroy($img);
        $this->registerTmpFile($path);

        return $path;
    }

    private function createTmpPng(int $width = 200, int $height = 150): string
    {
        $path = sys_get_temp_dir().'/picdiet_imagick_test_'.uniqid().'.png';
        $img = imagecreatetruecolor($width, $height);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefilledrectangle($img, 0, 0, $width, $height, $transparent);
        imagepng($img, $path);
        imagedestroy($img);
        $this->registerTmpFile($path);

        return $path;
    }

    private function createTmpTextFile(): string
    {
        $path = sys_get_temp_dir().'/picdiet_imagick_test_'.uniqid().'.txt';
        file_put_contents($path, 'this is not an image');
        $this->registerTmpFile($path);

        return $path;
    }

    private function registerResponseFile(?\PicDiet\Dto\CompressionResponse $response): void
    {
        if (null !== $response?->path) {
            $this->registerTmpFile($response->path);
        }
    }
}
