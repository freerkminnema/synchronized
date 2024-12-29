<?php

namespace FreerkMinnema\Synchronized\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Additional setup logic here
    }
}
