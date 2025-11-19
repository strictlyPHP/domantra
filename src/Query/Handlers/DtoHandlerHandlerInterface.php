<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query\Handlers;

use StrictlyPHP\Domantra\Domain\CachedDtoInterface;
use StrictlyPHP\Domantra\Query\Exception\ItemNotFoundException;

interface DtoHandlerHandlerInterface
{
    /**
     * @throws ItemNotFoundException
     */
    public function __invoke(object $query): CachedDtoInterface;
}
