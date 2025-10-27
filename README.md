# Domantra

[![Coverage Status](https://coveralls.io/repos/github/strictlyPHP/domantra/badge.svg?branch=main)](https://coveralls.io/github/strictlyPHP/domantra?branch=main)
![CI Status](https://github.com/strictlyPHP/domantra/actions/workflows/test-main.yml/badge.svg)
![Stable](https://img.shields.io/packagist/v/strictlyphp/domantra)

A PHP library implementing Domain-Driven Design (DDD) patterns and CQRS architecture. Domantra provides a solid foundation for building scalable, maintainable applications following DDD principles with command and query separation.

## Features

- Command and Query Buses for CQRS
- Domain Event Dispatching
- Pluggable Caching and Logging
- Easy integration with existing PHP projects

## Requirements

- PHP 8.2 or higher
- Composer 2.0+

## Basic Usage

It's recommended to use value objects for identifiers in your domain models.

```php

namespace App\ValueObject;

class UserId
{
    public function __construct(
        public readonly string $value
    ) {}
}
```
Create your domain models:
 * Your domain models should extend the `StrictlyPHP\Domantra\Domain\AbstractAggregateRoot` class.
 * Your event classes should implement the `StrictlyPHP\Domantra\Command\EventInterface`.
 * The UseTimestamps attribute can be used to automatically handle createdAt, updatedAt and deletedAt timestamps.
 * Use the `recordAndApplyThat` method to record an event and apply it to the model.

Your event class will look like this:
```php
namespace App\Domain\User\Event;

use StrictlyPHP\Domantra\Command\EventInterface;

readonly class UserWasCreated implements EventInterface
{
    public function __construct(
        public readonly UserId $id,
        public readonly string $username,
        public readonly string $email,
        public readonly DateTimeImmutable $happenedAt
    ) {}
}
````

Your domain model will look like this:

```php
namespace App\Domain\User;

use StrictlyPHP\Domantra\Domain\UseTimestamps;
use App\ValueObject\UserId;
use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;
use App\Domain\User\Event\UserWasCreated;

#[UseTimestamps]
class User extends AbstractAggregateRoot
{
    public readonly UserId $id;
    public readonly string $username;
    public readonly string $email;
    
    public function __construct(
    ) {}
    
    public static function create(
        UserId $id,
        string $username,
        string $email,
        DateTimeImmutable $happenedAt
    ): self {
        $user = new self();
        $model->recordAndApplyThat(
            new UserWasCreated(
                $id,
                $username,
                $email,
                $happenedAt
            )  
        );
        return $user;   
    }
    
    public function applyThatUserWasCreated(UserWasCreated $event): void
    {
        $this->id = $event->id;
        $this->username = $event->username;
        $this->email = $event->email;
    }   
}
```

### Command Handling

Your command will look like this:
```php

namespace App\Domain\User\Command;

use App\ValueObject\UserId;
use DateTimeImmutable;

class CreateUserCommand
{
    public UserId $id;
    public string $username;
    public string $email;

    public function __construct(UserId $id, string $username, string $email, DateTimeImmutable $happenedAt)
    {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
        $this->happenedAt = $happenedAt;   
    }
}
```

Your command handler will look like this:

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
        
        /** 
            Add your own code to persist the user entity (e.g., save to a database) 
        **/
        
        return $user;
    }
}

```

The system will automatically cache your models, dispatch events for you and update timestamps if you used the `UseTimestamps` attribute.

You can then use the command bus to dispatch the command:

```php

use Domantra\CommandBus;

$commandBus = CommandBus::create();
$commandBus->registerHandler(CreateUserCommand::class, new CreateUserHandler());

$command = new CreateUserCommand('john_doe', 'john@example.com');
$commandBus->dispatch($command);
```

### Query Handling

```php
<?php

namespace App\Domain\User\Query;

use App\ValueObject\UserId;
use App\Domain\User\User;

class GetUserByIdHandler
{
    public function __invoke(UserId $query) : User
    {
        // Fetch user data by ID (e.g., from a database)
        // Return user data as an array or DTO
        return [
            'id' => $query->userId,
            'username' => 'john_doe',
            'email' => 'john@example.com',
        ];
    }
}
```

You can then use the query bus to dispatch the query:

```php

use Domantra\QueryBus;

$queryBus = new QueryBus();
$queryBus->registerHandler(UserId::class, new GetUserByIdHandler());

$userId = new UserId('user-1');
$result = $queryBus->dispatch($userId);

```

The system will try to fetch the model from the cache first, and if it's not found, it will dispatch the query to the handler.

