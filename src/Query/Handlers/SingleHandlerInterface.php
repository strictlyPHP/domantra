<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query\Handlers;

use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;
use StrictlyPHP\Domantra\Query\Exception\ModelNotFoundException;

interface SingleHandlerInterface
{
    /**
     * @throws ModelNotFoundException
     */
    public function __invoke(object $query): AbstractAggregateRoot;
}
