<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Domain;

/**
 * @template T
 *
 * @implements PaginatedIdCollectionInterface<T>
 */
class PaginatedIdCollection implements PaginatedIdCollectionInterface
{
    /**
     * @var array<int, T>
     */
    private array $ids = [];

    private int $page;

    private int $perPage;

    private int $totalItems;

    /**
     * @param array<int, T> $ids
     */
    public function __construct(
        array $ids = [],
        int $page = 1,
        ?int $perPage = null,
        ?int $totalItems = null,
    ) {
        $this->page = $page;
        $this->perPage = $perPage ?? count($ids);
        $this->totalItems = $totalItems ?? count($ids);

        $this->ids = $ids;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->ids);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->ids[$offset]);
    }

    /**
     * @return T
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->ids[$offset];
    }

    /***
     * @param T $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->ids[] = $value;
        } else {
            $this->ids[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->ids[$offset]);
    }

    public function count(): int
    {
        return count($this->ids);
    }

    public function jsonSerialize(): \stdClass
    {
        return (object) [
            'ids' => $this->ids,
            'page' => $this->page,
            'perPage' => $this->perPage,
            'totalItems' => $this->totalItems,
        ];
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }
}
