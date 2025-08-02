<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query;

use StrictlyPHP\Domantra\Query\Handlers\PaginatedHandlerInterface;
use StrictlyPHP\Domantra\Query\Response\ResponseInterface;

interface QueryBusInterface
{
    /**
     * @template T
     * @param class-string $queryClass
     * @param PaginatedHandlerInterface<T> $handler
     */
    public function registerHandler(string $queryClass, PaginatedHandlerInterface $handler): void;

    public function handle(object $query): ResponseInterface;
}
