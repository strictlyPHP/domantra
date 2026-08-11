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
        \DateTimeInterface $happenedAt,
    ): void {
        $happenedAt = \DateTimeImmutable::createFromInterface($happenedAt);
        $classArray = explode('\\', get_class($event));
        $class = end($classArray);
        $method = sprintf('applyThat%s', $class);

        if (! method_exists($this, $method)) {
            throw new \RuntimeException(sprintf('Missing apply method %s in %s', $method, static::class));
        }

        $previousDto = $this->snapshotDto();

        $this->$method($event);

        $useTimestampsAttributes = (new \ReflectionClass($this))->getAttributes(UseTimestamps::class);
        $useTimestamps = $useTimestampsAttributes !== [];
        $softDelete = $useTimestamps && $useTimestampsAttributes[0]->newInstance()->softDelete;

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
            dto : json_decode(json_encode($this->getDto())),
            previousDto: $previousDto
        );
    }

    /**
     * Snapshot of the DTO as it was before the event is applied, or null when
     * the aggregate has no previous state (i.e. this is its first event).
     */
    private function snapshotDto(): ?\stdClass
    {
        try {
            return json_decode(json_encode($this->getDto()));
        } catch (\Error $e) {
            // getDto() legitimately fails before the first event, while the
            // aggregate's own properties are still uninitialized. If they are
            // all initialized, the Error is a genuine bug in getDto().
            foreach ((new \ReflectionObject($this))->getProperties() as $property) {
                if ($property->isStatic() || $property->getDeclaringClass()->getName() === self::class) {
                    continue;
                }
                if (! $property->isInitialized($this)) {
                    return null;
                }
            }
            throw $e;
        }
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

    public function hasPendingEvents(): bool
    {
        return sizeof($this->_eventLogItems) > 0;
    }

    abstract public function getDto(): CachedDtoInterface;
}
