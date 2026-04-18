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
     * @param ExpansionPolicy $expansionPolicy Whether this handler may be used for expansion, and if so
     *                                         whether it expands by default or only on explicit request.
     *                                         Defaults to {@see ExpansionPolicy::Disabled}: the handler is
     *                                         reachable only via direct {@see self::handle()} calls.
     */
    public function registerHandler(string $queryClass, SingleHandlerInterface|PaginatedHandlerInterface|DtoHandlerHandlerInterface $handler, ExpansionPolicy $expansionPolicy = ExpansionPolicy::Disabled): void;

    /**
     * @param list<string>|null $expand Optional allow-list of DTO property names to expand.
     *                                  `null` expands every property whose handler was registered with
     *                                  {@see ExpansionPolicy::ByDefault}. An empty array disables expansion.
     *                                  Non-empty arrays expand only the named source-DTO property (match is on
     *                                  the original property name, e.g. `profileId`, not the derived output key)
     *                                  and are honoured regardless of whether the handler's policy is `ByDefault`
     *                                  or `OnRequest`. Names referring to handlers registered with
     *                                  {@see ExpansionPolicy::Disabled} are silently skipped.
     */
    public function handle(object $query, ?string $role = null, ?array $expand = null): ResponseInterface;
}
