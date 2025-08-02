<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query\Handlers;

use StrictlyPHP\Domantra\Domain\PaginatedIdCollection;

/**
 * @template T
 */
interface PaginatedHandlerInterface
{
    /**
     * @return PaginatedIdCollection<T>
     */
    public function __invoke(object $query): PaginatedIdCollection;
}
