<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Fixtures\Cache;

use Predis\Client;

/**
 * Predis\Client::set is dispatched via __call and has no declared signature,
 * so PHPUnit cannot double it without MockBuilder::addMethods (removed in
 * PHPUnit 12). Declaring it abstract here gives createMock() a real method
 * to override.
 */
abstract class PredisClientStub extends Client
{
    abstract public function set(mixed ...$arguments): mixed;
}
