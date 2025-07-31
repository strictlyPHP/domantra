<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query;

interface ResponseInterface extends \JsonSerializable
{
    public function jsonSerialize(): object;
}
