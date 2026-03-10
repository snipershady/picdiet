<?php

namespace PicDiet\Tests;

use PicDiet\Service\ImageCompressorService;

/**
 * Description of AbstractTestCase.
 *
 * @author Stefano Perrini <perrini.stefano@gmail.com>
 */
class TestCompression extends AbstractTestCase
{
    public function testOne(): void
    {
        $this->assertTrue(true);
    }

    public function testCompressDownloadedImage(): void
    {
        $imageUrl = 'https://www.php.net/manual/en/images/0baa1b9fae6aec55bbb73037f3016001-xkcd-goto.png';
        $tmpPath = '/tmp/xkcd-goto.png';

        $downloaded = file_put_contents($tmpPath, file_get_contents($imageUrl));
        $this->assertNotFalse($downloaded, 'Download of test image failed');

        $service = new ImageCompressorService();
        $response = $service->compress($tmpPath);

        $this->assertTrue($response->success);
        echo "original size: ". $response->originalSize . PHP_EOL;
        echo "compress size: ". $response->compressedSize . PHP_EOL;
        $this->assertTrue($response->originalSize > $response->compressedSize);
    }
}
