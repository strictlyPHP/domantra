<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Command;

use Exception;
use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;
use Throwable;

class CommandException extends Exception
{
    /**
     * @param AbstractAggregateRoot|AbstractAggregateRoot[] $model
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly AbstractAggregateRoot|array|null $model = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
