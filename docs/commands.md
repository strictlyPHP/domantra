# Commands

## `CommandInterface`

A marker interface — commands are data carriers:

```php
use StrictlyPHP\Domantra\Command\CommandInterface;

class CreateUserCommand implements CommandInterface
{
    public function __construct(
        public readonly UserId $id,
        public readonly string $username,
        public readonly string $email,
        public readonly \DateTimeImmutable $happenedAt
    ) {}
}
```

## Command Handlers

Handlers are invokable classes that accept a single `CommandInterface` parameter and return an `AbstractAggregateRoot` (or an array of them, or `null`):

```php
class CreateUserHandler
{
    public function __invoke(CreateUserCommand $command): User
    {
        $user = User::create(
            $command->id,
            $command->username,
            $command->email,
            $command->happenedAt
        );

        // Persist the user (e.g., save to database)

        return $user;
    }
}
```

## `CommandBus`

### Creating a Bus

Use the static factory with optional dependencies:

```php
use StrictlyPHP\Domantra\Command\CommandBus;

// Minimal — uses NullLogger, EventBusMock, InMemory cache
$commandBus = CommandBus::create();

// With dependencies
$commandBus = CommandBus::create(
    logger: $psrLogger,
    eventBus: $myEventBus,
    cacheHandler: $redisCacheHandler
);
```

### Registering Handlers

```php
$commandBus->registerHandler(CreateUserCommand::class, new CreateUserHandler());
```

The bus validates that the handler accepts exactly one `CommandInterface` parameter.

### Dispatching

```php
$command = new CreateUserCommand(
    new UserId('user-123'),
    'john_doe',
    'john@example.com',
    new \DateTimeImmutable()
);

$commandBus->dispatch($command);
```

### Dispatch Flow

1. The registered handler is invoked with the command
2. The handler returns an `AbstractAggregateRoot` (or array of them)
3. For each aggregate, pending events are extracted via `_getEventLogItems()`
4. Each `EventLogItem` is dispatched through the `EventBusInterface`
5. The aggregate's DTO is cached via `$cacheHandler->set($aggregate->getDto())`
6. If no events were recorded, a `RuntimeException` is thrown

### `CommandException`

If a handler needs to signal a partial failure while still processing events and caching:

```php
use StrictlyPHP\Domantra\Command\CommandException;

throw new CommandException(
    message: 'External service failed',
    model: $user  // events will still be dispatched and DTO cached
);
```

The `CommandBus` catches `CommandException`, processes the attached model's events and DTO, then re-throws the exception.
