<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query;

interface QueryBusInterface
{
    /**
     * @param class-string $queryClass
     */
    public function registerHandler(string $queryClass, callable $handler): void;

    public function handle(object $query): ResponseInterface;
}
