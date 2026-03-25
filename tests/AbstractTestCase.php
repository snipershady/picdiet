<?php

namespace PicDiet\Tests;

use PHPUnit\Framework\TestCase;

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
}
