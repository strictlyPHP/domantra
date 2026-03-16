<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query;

use StrictlyPHP\Domantra\Query\Handlers\DtoHandlerHandlerInterface;
use StrictlyPHP\Domantra\Query\Handlers\PaginatedHandlerInterface;
use StrictlyPHP\Domantra\Query\Handlers\SingleHandlerInterface;
use StrictlyPHP\Domantra\Query\Response\ResponseInterface;

interface QueryBusInterface
{
    /**
     * @template T
     * @param class-string $queryClass
     * @param SingleHandlerInterface|PaginatedHandlerInterface<T>|DtoHandlerHandlerInterface $handler
     */
    public function registerHandler(string $queryClass, SingleHandlerInterface|PaginatedHandlerInterface|DtoHandlerHandlerInterface $handler, bool $allowExpansion = false): void;

    public function handle(object $query, ?string $role = null): ResponseInterface;
}
