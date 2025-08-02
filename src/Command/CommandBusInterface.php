<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Command;

interface CommandBusInterface
{
    /**
     * @param class-string $commandClass
     */
    public function registerHandler(string $commandClass, callable $handler): void;

    public function dispatch(CommandInterface $command): void;
}
