<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Monolog\Handler\NullHandler;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Durante los tests el canal "security" descarta todo:
        // así los tests no ensucian storage/logs/security-*.log, que es evidencia real.
        config(['logging.channels.security' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ]]);
    }
}
