<?php

namespace MetaSyntactical\Mime\Tests;

use MetaSyntactical\Mime\Exception\FileNotFoundException;
use MetaSyntactical\Mime\Magic;
use PHPUnit\Framework\TestCase;

class MagicTest extends TestCase
{
    /**
     * @var Magic
     */
    protected $object;

    protected function setUp(): void
    {
        $this->object = new Magic;
    }

    protected function tearDown(): void
    {
        Magic::setDefaultMagicFile();
    }

    public function testExpectedExceptionIsThrownIfProvidedMagicFileDoesNotExist(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('File does not exist or is not readable:');

        new Magic('/notExistingDummyPath/magic');
    }

    public function testSettingNonExistingDefaultMagicFileThrowsException(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('/notExistingDummyPath/magic');

        Magic::setDefaultMagicFile('/notExistingDummyPath/magic');
    }

    public function testDefaultMagicFileIsUsedOnInitialization(): void
    {
        Magic::setDefaultMagicFile(__DIR__ . '/_Data/magic');
        self::assertNull((new Magic())->getMimeType(__DIR__ . '/_Data/magic'));
    }

    public function testRetrievingMimeTypeReturnsExpectedResults(): void
    {
        self::assertEquals(
            'image/jpeg',
            $this->object->getMimeType(__DIR__ . '/_Data/Fireworks_Australia_Day_11_-_2_(Public_Domain).jpg')
        );

        $smallMagic = new Magic(__DIR__ . '/_Data/magic');
        self::assertNull($smallMagic->getMimeType(__DIR__ . '/_Data/magic'));
        self::assertNull($this->object->getMimeType(__DIR__ . '/_Data/magic'));
    }

    public function testCheckingFilesHaveExpectedMimeTypes(): void
    {
        self::assertTrue(
            $this->object->isMimeType(__DIR__ . '/_Data/Fireworks_Australia_Day_11_-_2_(Public_Domain).jpg', 'image/jpeg')
        );
        $fileArray = array(__DIR__ . '/_Data/Fireworks_Australia_Day_11_-_2_(Public_Domain).jpg');
        self::assertEquals(array(true), $this->object->isMimeType($fileArray, 'image/jpeg'));
    }
}
