<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Domain;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * @template T
 *
 * @extends IteratorAggregate<int, T>
 * @extends ArrayAccess<int, T>
 */
interface PaginatedIdCollectionInterface extends PaginationInterface, IteratorAggregate, JsonSerializable, ArrayAccess, Countable
{
}
