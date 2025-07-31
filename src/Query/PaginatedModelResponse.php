<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query;

/**
 * @template T
 */
class PaginatedModelResponse implements ResponseInterface
{
    public readonly int $perPage;

    public readonly int $totalItems;

    /**
     * @param array<int, T> $items
     */
    public function __construct(
        public readonly array $items = [],
        public readonly int $page = 1,
        ?int $perPage = null,
        ?int $totalItems = null,
    ) {
        $this->perPage = $perPage ?? count($items);
        $this->totalItems = $totalItems ?? count($items);
    }

    public function jsonSerialize(): \stdClass
    {
        return (object) [
            'items' => $this->items,
            'page' => $this->page,
            'perPage' => $this->perPage,
            'totalItems' => $this->totalItems,
        ];
    }
}
