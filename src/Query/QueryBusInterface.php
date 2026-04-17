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

    /**
     * @param list<string>|null $expand Optional allow-list of DTO property names to expand.
     *                                  `null` expands every property whose handler was registered with
     *                                  `allowExpansion: true` (default). An empty array disables expansion.
     *                                  Non-empty arrays expand only the named source-DTO property (match is on
     *                                  the original property name, e.g. `profileId`, not the derived output key).
     *                                  Authorization still wins: names referring to handlers registered without
     *                                  `allowExpansion: true` are silently skipped.
     */
    public function handle(object $query, ?string $role = null, ?array $expand = null): ResponseInterface;
}
