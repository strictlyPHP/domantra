# Aggregate Roots

Aggregate roots are your domain models. They extend `AbstractAggregateRoot` and use events to record state changes.

## Basic Structure

```php
use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;
use StrictlyPHP\Domantra\Domain\CachedDtoInterface;
use StrictlyPHP\Domantra\Domain\UseTimestamps;

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

    public function updateUsername(string $username, \DateTimeImmutable $happenedAt): void
    {
        $this->recordAndApplyThat(
            new UsernameWasUpdated($username),
            $happenedAt
        );
    }

    protected function applyThatUsernameWasUpdated(UsernameWasUpdated $event): void
    {
        $this->username = $event->username;
    }

    public function getDto(): UserDto
    {
        return new UserDto($this->id, $this->username, $this->email);
    }
}
```

## Key Concepts

### `recordAndApplyThat(EventInterface $event, DateTimeImmutable $happenedAt)`

This method:
1. Derives the apply method name from the event class: `FooHappened` calls `applyThatFooHappened()`
2. Calls the apply method to mutate the aggregate's state
3. Updates timestamps if `#[UseTimestamps]` is present
4. Records an `EventLogItem` (event + timestamp + DTO snapshot) for later dispatch

The event itself should **not** contain timing information — the `$happenedAt` parameter is passed separately.

### `getDto(): CachedDtoInterface`

Every aggregate root must implement this method, returning a **concrete** DTO class (not the `CachedDtoInterface` type). The return type is inspected via reflection to resolve cache lookups. See [DTOs & Caching](dtos-and-caching.md).

### Protected Constructor

The constructor is `protected` — use named static constructors (e.g. `User::create()`) to enforce that state changes always go through events.

## `UseTimestamps` Attribute

Automatically manages `createdAt`, `updatedAt`, and `deletedAt` properties:

```php
#[UseTimestamps]                    // createdAt + updatedAt
#[UseTimestamps(softDelete: true)]  // createdAt + updatedAt + deletedAt
```

- On the **first** event: sets `createdAt`, `updatedAt` is `null`
- On subsequent events: updates `updatedAt`
- With `softDelete: true`: initializes `deletedAt` to `null`

Access via `getCreatedAt()`, `getUpdatedAt()`, `getDeletedAt()`.

## Event Log

The aggregate tracks all uncommitted events internally:

- `_getEventLogItems()` — returns pending `EventLogItem[]`
- `_clearEventLogItems()` — clears the log (called by the query bus after cache-miss reads)
- `hasPendingEvents()` — checks if there are uncommitted events
