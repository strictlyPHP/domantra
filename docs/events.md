# Events

## `EventInterface`

A marker interface — events are readonly data carriers with no methods to implement:

```php
use StrictlyPHP\Domantra\Command\EventInterface;

readonly class UserWasCreated implements EventInterface
{
    public function __construct(
        public UserId $id,
        public string $username,
        public string $email
    ) {}
}
```

Events should **not** contain timing information. The `$happenedAt` timestamp is passed as a separate argument to `recordAndApplyThat()`.

## `EventLogItem`

When an event is recorded on an aggregate, it is wrapped in an `EventLogItem`:

```php
readonly class EventLogItem
{
    public function __construct(
        public string $name,                // e.g. "app.domain.user.event.userWasCreated"
        public EventInterface $event,       // the original event
        public \DateTimeImmutable $happenedAt,
        public \stdClass $dto,              // DTO snapshot at the time of the event
    ) {}
}
```

The `name` is derived from the fully-qualified class name of the event, converted to dot-notation with `lcfirst` segments.

## `EventBusInterface`

Implement this to publish events to your infrastructure (message queue, event store, etc.):

```php
use StrictlyPHP\Domantra\Command\EventBusInterface;
use StrictlyPHP\Domantra\Domain\EventLogItem;

class MyEventBus implements EventBusInterface
{
    public function dispatch(EventLogItem $eventLogItem): void
    {
        // Publish to RabbitMQ, Kafka, database, etc.
    }
}
```

## `EventBusMock`

A no-op implementation for testing or when you don't need event publishing:

```php
use StrictlyPHP\Domantra\Command\EventBusMock;

$commandBus = CommandBus::create(eventBus: new EventBusMock());
```

`EventBusMock` is also the default when using `CommandBus::create()` without arguments.

## Automatic Dispatch

You don't dispatch events manually. The `CommandBus` automatically extracts and dispatches all pending events from the aggregate root(s) returned by a command handler. See [Commands](commands.md).
