<?php

namespace PicDiet\Tests;

use PHPUnit\Framework\TestCase;
use PicDiet\Dto\CompressionResponse;

/**
 * Description of AbstractTestCase.
 *
 * @author Stefano Perrini <perrini.stefano@gmail.com>
 */
abstract class AbstractTestCase extends TestCase
{
    /** @var string[] */
    private array $tmpFiles = [];

    /**
     * Registers a temporary file to be deleted after the test.
     */
    protected function registerTmpFile(string $path): void
    {
        $this->tmpFiles[] = $path;
    }

    #[\Override]
    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        $this->tmpFiles = [];

        parent::tearDown();
    }

    protected function createTmpJpeg(int $width = 200, int $height = 150): string
    {
        $path = sys_get_temp_dir().'/picdiet_test_'.uniqid().'.jpg';
        $img = imagecreatetruecolor($width, $height);
        imagefilledrectangle($img, 0, 0, $width, $height, imagecolorallocate($img, 255, 128, 0));
        imagejpeg($img, $path, 90);
        imagedestroy($img);
        $this->registerTmpFile($path);

        return $path;
    }

    protected function createTmpPng(int $width = 200, int $height = 150): string
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

    protected function createTmpWebp(int $width = 200, int $height = 150): string
    {
        $path = sys_get_temp_dir().'/picdiet_test_'.uniqid().'.webp';
        $img = imagecreatetruecolor($width, $height);
        imagefilledrectangle($img, 0, 0, $width, $height, imagecolorallocate($img, 0, 128, 255));
        imagewebp($img, $path, 90);
        imagedestroy($img);
        $this->registerTmpFile($path);

        return $path;
    }

    protected function createTmpTextFile(): string
    {
        $path = sys_get_temp_dir().'/picdiet_test_'.uniqid().'.txt';
        file_put_contents($path, 'this is not an image');
        $this->registerTmpFile($path);

        return $path;
    }

    protected function registerResponseFile(?CompressionResponse $response): void
    {
        if (null !== $response?->path) {
            $this->registerTmpFile($response->path);
        }
    }
}
