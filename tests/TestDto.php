<?php

namespace PicDiet\Tests;

use PicDiet\Dto\Dimensions;
use PicDiet\Dto\ImageInfo;

/**
 * @author Stefano Perrini <perrini.stefano@gmail.com>
 */
class TestDto extends AbstractTestCase
{
    // -------------------------------------------------------------------------
    // Dimensions — valid construction
    // -------------------------------------------------------------------------

    public function testDimensionsStoresWidthAndHeight(): void
    {
        $d = new Dimensions(800, 600);

        $this->assertSame(800, $d->width);
        $this->assertSame(600, $d->height);
    }

    public function testDimensionsAcceptsBoundaryValueOne(): void
    {
        $d = new Dimensions(1, 1);

        $this->assertSame(1, $d->width);
        $this->assertSame(1, $d->height);
    }

    // -------------------------------------------------------------------------
    // Dimensions — invalid construction
    // -------------------------------------------------------------------------

    public function testDimensionsThrowsOnZeroWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Dimensions(0, 100);
    }

    public function testDimensionsThrowsOnNegativeWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Dimensions(-1, 100);
    }

    public function testDimensionsThrowsOnZeroHeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Dimensions(100, 0);
    }

    public function testDimensionsThrowsOnNegativeHeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Dimensions(100, -1);
    }

    // -------------------------------------------------------------------------
    // ImageInfo — valid construction
    // -------------------------------------------------------------------------

    public function testImageInfoStoresAllProperties(): void
    {
        $info = new ImageInfo(1920, 1080, IMAGETYPE_JPEG, 'image/jpeg');

        $this->assertSame(1920, $info->width);
        $this->assertSame(1080, $info->height);
        $this->assertSame(IMAGETYPE_JPEG, $info->type);
        $this->assertSame('image/jpeg', $info->mime);
    }

    public function testImageInfoFromGetImageSizeFactoryMapsCorrectly(): void
    {
        $raw = [0 => 640, 1 => 480, 2 => IMAGETYPE_PNG, 'mime' => 'image/png'];
        $info = ImageInfo::fromGetImageSize($raw);

        $this->assertSame(640, $info->width);
        $this->assertSame(480, $info->height);
        $this->assertSame(IMAGETYPE_PNG, $info->type);
        $this->assertSame('image/png', $info->mime);
    }

    // -------------------------------------------------------------------------
    // ImageInfo — invalid construction
    // -------------------------------------------------------------------------

    public function testImageInfoThrowsOnZeroWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImageInfo(0, 100, IMAGETYPE_JPEG, 'image/jpeg');
    }

    public function testImageInfoThrowsOnNegativeWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImageInfo(-1, 100, IMAGETYPE_JPEG, 'image/jpeg');
    }

    public function testImageInfoThrowsOnZeroHeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImageInfo(100, 0, IMAGETYPE_JPEG, 'image/jpeg');
    }

    public function testImageInfoThrowsOnNegativeHeight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImageInfo(100, -1, IMAGETYPE_JPEG, 'image/jpeg');
    }

    public function testImageInfoThrowsOnZeroType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImageInfo(100, 100, 0, 'image/jpeg');
    }

    public function testImageInfoThrowsOnNegativeType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImageInfo(100, 100, -1, 'image/jpeg');
    }

    public function testImageInfoThrowsOnEmptyMime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImageInfo(100, 100, IMAGETYPE_JPEG, '');
    }

    public function testImageInfoThrowsOnBlankMime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImageInfo(100, 100, IMAGETYPE_JPEG, '   ');
    }

    public function testImageInfoThrowsOnNonImageMime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImageInfo(100, 100, IMAGETYPE_JPEG, 'application/octet-stream');
    }

    public function testImageInfoThrowsOnTextMime(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ImageInfo(100, 100, IMAGETYPE_JPEG, 'text/plain');
    }
}
