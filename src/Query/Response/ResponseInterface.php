<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query\Response;

interface ResponseInterface extends \JsonSerializable
{
    public function getCode(): int;

    public function jsonSerialize(): object;
}
