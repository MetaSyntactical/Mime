<?php

namespace MetaSyntactical\Mime\Exception;

use RuntimeException;

class FileNotFoundExceptionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FileNotFoundException
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new FileNotFoundException;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    public function testThatClassProvidesTheExpectedInterfaces()
    {
        self::assertInstanceOf(RuntimeException::class, $this->object);
        self::assertInstanceOf(Exception::class, $this->object);
    }
}
