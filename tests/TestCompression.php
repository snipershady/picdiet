<?php

namespace PicDiet\Tests;

use PicDiet\Enum\ImageFormatEnum;
use PicDiet\Service\ImageCompressorService;

/**
 * @author Stefano Perrini <perrini.stefano@gmail.com>
 */
class TestCompression extends AbstractTestCase
{
    private ImageCompressorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ImageCompressorService();
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
    // Integration
    // -------------------------------------------------------------------------

    public function testCompressDownloadedImage(): void
    {
        $imageUrl = 'https://www.php.net/manual/en/images/0baa1b9fae6aec55bbb73037f3016001-xkcd-goto.png';
        $tmpPath = '/tmp/xkcd-goto.png';

        $downloaded = file_put_contents($tmpPath, file_get_contents($imageUrl));
        $this->assertNotFalse($downloaded, 'Download of test image failed');
        $this->registerTmpFile($tmpPath);

        $response = $this->service->compress($tmpPath);
        $this->registerResponseFile($response);

        $this->assertTrue($response->success);
        $this->assertGreaterThan($response->compressedSize, $response->originalSize);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function createTmpJpeg(int $width = 200, int $height = 150): string
    {
        $path = sys_get_temp_dir().'/picdiet_test_'.uniqid().'.jpg';
        $img = imagecreatetruecolor($width, $height);
        imagefilledrectangle($img, 0, 0, $width, $height, imagecolorallocate($img, 255, 128, 0));
        imagejpeg($img, $path, 90);
        imagedestroy($img);
        $this->registerTmpFile($path);

        return $path;
    }

    private function createTmpPng(int $width = 200, int $height = 150): string
    {
        $path = sys_get_temp_dir().'/picdiet_test_'.uniqid().'.png';
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
        $path = sys_get_temp_dir().'/picdiet_test_'.uniqid().'.txt';
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
