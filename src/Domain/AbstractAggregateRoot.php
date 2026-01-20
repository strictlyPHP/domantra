<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Domain;

use StrictlyPHP\Domantra\Command\EventInterface;

abstract class AbstractAggregateRoot
{
    protected \DateTimeImmutable $createdAt;

    protected ?\DateTimeImmutable $updatedAt;

    protected ?\DateTimeImmutable $deletedAt;

    /**
     * @var EventLogItem[]
     */
    private array $_eventLogItems = [];

    /**
     * We don't want to be able to call the constructor directly
     * Use a named constructor instead.
     */
    protected function __construct()
    {
    }

    protected function recordAndApplyThat(
        EventInterface $event,
        \DateTimeImmutable $happenedAt,
    ): void {
        $classArray = explode('\\', get_class($event));
        $class = end($classArray);
        $method = sprintf('applyThat%s', $class);

        if (! method_exists($this, $method)) {
            throw new \RuntimeException(sprintf('Missing apply method %s in %s', $method, static::class));
        }

        $this->$method($event);

        $useTimestampsAttributes = (new \ReflectionClass($this))->getAttributes(UseTimestamps::class);
        $useTimestamps = (bool) $useTimestampsAttributes;
        $softDelete = $useTimestampsAttributes[0]->newInstance()->softDelete ?? false;

        if ($useTimestamps) {
            if (! isset($this->createdAt)) {
                $this->createdAt = $happenedAt;
                $this->updatedAt = null;
            } else {
                $this->updatedAt = $happenedAt;
            }
            if (! isset($this->deletedAt) && $softDelete === true) {
                $this->deletedAt = null;
            }
        }

        $this->_eventLogItems[] = new EventLogItem(
            name: implode('.', array_map(fn (string $item) => lcfirst($item), $classArray)),
            event: $event,
            happenedAt: $happenedAt,
            dto : json_decode(json_encode($this->getDto()))
        );
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * @return EventLogItem[]
     */
    public function _getEventLogItems(): array
    {
        return $this->_eventLogItems;
    }

    public function _clearEventLogItems(): void
    {
        $this->_eventLogItems = [];
    }

    abstract public function getDto(): CachedDtoInterface;
}
