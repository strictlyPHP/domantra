<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query;

/**
 * Controls whether a registered handler may be used to expand a value object
 * appearing as a property on another DTO, and if so, whether expansion happens
 * implicitly when the caller does not pass an explicit `$expand` list to
 * {@see QueryBusInterface::handle()}.
 */
enum ExpansionPolicy
{
    /**
     * Handler is never used for expansion. The registered query class is only
     * reachable via a direct {@see QueryBusInterface::handle()} call.
     */
    case Disabled;

    /**
     * Handler may be used for expansion only when the caller names the
     * source-DTO property in the `$expand` list passed to
     * {@see QueryBusInterface::handle()}. Passing `$expand = null` does not
     * auto-expand this handler. Use this when the same value object appears
     * in DTOs where expansion is not wanted, so expansion must be opted-in
     * per call site.
     */
    case OnRequest;

    /**
     * Handler is used for expansion whenever eligible, including when the
     * caller passes `$expand = null`. Callers can still suppress expansion
     * for a specific call by passing `$expand = []` or by naming only other
     * properties.
     */
    case ByDefault;
}
