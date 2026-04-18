<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query\Exception;

use Throwable;

/**
 * Marker interface for "not found" exceptions raised by query handlers.
 *
 * When a handler is registered with {@see \StrictlyPHP\Domantra\Query\QueryBus::registerHandler()}
 * under an {@see \StrictlyPHP\Domantra\Query\ExpansionPolicy} other than `Disabled`, DTO
 * expansion catches any exception implementing this interface and substitutes `null` for the
 * expanded value. Any other exception bubbles and fails the enclosing request.
 *
 * Consumers that share a handler with an HTTP route (and therefore throw a framework exception
 * on a miss) can implement this interface on their own exception type without Domantra taking
 * a dependency on any HTTP library.
 */
interface ItemNotFoundExceptionInterface extends Throwable
{
}
