<?php

namespace MetaSyntactical\Mime\Tests\Exception;

use Exception;
use MetaSyntactical\Mime\Exception\FileNotFoundException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FileNotFoundExceptionTest extends TestCase
{
    /**
     * @var FileNotFoundException
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new FileNotFoundException;
    }

    public function testThatClassProvidesTheExpectedInterfaces()
    {
        self::assertInstanceOf(RuntimeException::class, $this->object);
        self::assertInstanceOf(Exception::class, $this->object);
    }
}
