# Domantra

[![Coverage Status](https://coveralls.io/repos/github/strictlyPHP/domantra/badge.svg?branch=main)](https://coveralls.io/github/strictlyPHP/domantra?branch=main)
![CI Status](https://github.com/strictlyPHP/domantra/actions/workflows/test-main.yml/badge.svg)
![Stable](https://img.shields.io/packagist/v/strictlyphp/domantra)

A PHP library implementing Domain-Driven Design (DDD) patterns and CQRS architecture. Domantra provides a solid foundation for building scalable, maintainable applications following DDD principles with command and query separation.

## Features

- Command and Query Buses for CQRS
- Domain Event Dispatching
- Automatic DTO Caching (InMemory, Redis, Predis)
- Role-Based Property Access Control
- Paginated Query Support with DTO Expansion
- Pluggable Logging via PSR-3

## Requirements

- PHP 8.2 or higher
- Composer 2.0+

## Installation

```bash
composer require strictlyphp/domantra
```

## Quick Start

### 1. Define a Value Object ID

IDs must implement `\Stringable` for cache key resolution:

```php
namespace App\ValueObject;

use StrictlyPHP\Domantra\ValueObject\StringValueObject;
use StrictlyPHP\Domantra\ValueObject\ValueObject;

class UserId implements StringValueObject
{
    public function __construct(private readonly string $id) {}

    public function __toString(): string { return $this->id; }
    public function jsonSerialize(): string { return $this->id; }
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->id === $other->id;
    }
}
```

### 2. Create an Event

Events implement `EventInterface` and carry data only — no timestamps:

```php
namespace App\Domain\User\Event;

use StrictlyPHP\Domantra\Command\EventInterface;
use App\ValueObject\UserId;

readonly class UserWasCreated implements EventInterface
{
    public function __construct(
        public UserId $id,
        public string $username,
        public string $email
    ) {}
}
```

### 3. Create a DTO

DTOs implement `CachedDtoInterface` for automatic caching:

```php
namespace App\Domain\User;

use StrictlyPHP\Domantra\Domain\CachedDtoInterface;
use App\ValueObject\UserId;

readonly class UserDto implements CachedDtoInterface
{
    public function __construct(
        public UserId $id,
        public string $username,
        public string $email
    ) {}

    public function getCacheKey(): string { return (string) $this->id; }
    public function getTtl(): int { return 3600; }
}
```

### 4. Build the Aggregate Root

```php
namespace App\Domain\User;

use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;
use StrictlyPHP\Domantra\Domain\UseTimestamps;
use App\ValueObject\UserId;
use App\Domain\User\Event\UserWasCreated;

#[UseTimestamps]
class User extends AbstractAggregateRoot
{
    private UserId $id;
    private string $username;
    private string $email;

    public static function create(
        UserId $id,
        string $username,
        string $email,
        \DateTimeImmutable $happenedAt
    ): self {
        $user = new self();
        $user->recordAndApplyThat(
            new UserWasCreated($id, $username, $email),
            $happenedAt
        );
        return $user;
    }

    protected function applyThatUserWasCreated(UserWasCreated $event): void
    {
        $this->id = $event->id;
        $this->username = $event->username;
        $this->email = $event->email;
    }

    public function getDto(): UserDto
    {
        return new UserDto($this->id, $this->username, $this->email);
    }
}
```

### 5. Command & Handler

```php
namespace App\Domain\User\Command;

use StrictlyPHP\Domantra\Command\CommandInterface;
use App\ValueObject\UserId;

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

```php
namespace App\Domain\User\Command;

use App\Domain\User\User;

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

### 6. Dispatch via Command Bus

```php
use StrictlyPHP\Domantra\Command\CommandBus;
use App\Domain\User\Command\CreateUserCommand;
use App\Domain\User\Command\CreateUserHandler;
use App\ValueObject\UserId;

$commandBus = CommandBus::create();
$commandBus->registerHandler(CreateUserCommand::class, new CreateUserHandler());

$commandBus->dispatch(new CreateUserCommand(
    new UserId('user-123'),
    'john_doe',
    'john@example.com',
    new \DateTimeImmutable()
));
```

The bus automatically dispatches events and caches the DTO.

### 7. Query Handling

```php
namespace App\Domain\User\Query;

use StrictlyPHP\Domantra\Query\Handlers\SingleHandlerInterface;
use App\Domain\User\User;

class GetUserByIdHandler implements SingleHandlerInterface
{
    public function __invoke(object $query): User
    {
        // Fetch user from database by $query (a UserId)
        // Return the reconstructed aggregate root
    }
}
```

```php
use StrictlyPHP\Domantra\Query\QueryBus;
use StrictlyPHP\Domantra\Query\AggregateRootHandler;
use StrictlyPHP\Domantra\Query\CachedDtoHandler;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerInMemory;
use App\ValueObject\UserId;

$cacheHandler = new DtoCacheHandlerInMemory();
$queryBus = new QueryBus(
    new AggregateRootHandler($cacheHandler),
    new CachedDtoHandler($cacheHandler)
);

$queryBus->registerHandler(UserId::class, new GetUserByIdHandler());

$response = $queryBus->handle(new UserId('user-123'));
// Returns ModelResponse with $response->item
```

The query bus checks the cache first. On a miss, it invokes the handler, caches the DTO, and returns it.

## Testing

All commands run inside Docker — no local PHP extensions are required.

```bash
make install           # Install dependencies
make check-coverage    # Run tests with coverage check on changed files
make style             # Check coding style
make style-fix         # Auto-fix coding style
make analyze           # Run static analysis (PHPStan)
```

Run `make help` to see all available commands.

## Documentation

For detailed guides on all features, see the [docs/](docs/README.md) directory:

- [Installation](docs/installation.md)
- [Value Objects](docs/value-objects.md)
- [Aggregate Roots](docs/aggregate-roots.md)
- [Events](docs/events.md)
- [DTOs & Caching](docs/dtos-and-caching.md)
- [Commands](docs/commands.md)
- [Queries](docs/queries.md) — pagination, expansion, handler types
- [Role-Based Access](docs/role-based-access.md)
- [Clock](docs/clock.md) — time abstraction for testing
