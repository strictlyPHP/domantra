<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Domain;

interface PaginationInterface
{
    public function getPage(): int;

    public function getPerPage(): int;

    public function getTotalItems(): int;
}
