# Clock

Domantra provides a simple time abstraction for testability.

## `ClockInterface`

```php
use StrictlyPHP\Domantra\Time\ClockInterface;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
```

## `SystemClock`

The default implementation returns the current time:

```php
use StrictlyPHP\Domantra\Time\SystemClock;

$clock = new SystemClock();
$now = $clock->now(); // DateTimeImmutable
```

## Testing with a Fake Clock

Create a fake implementation for deterministic tests:

```php
class FakeClock implements ClockInterface
{
    public function __construct(
        private \DateTimeImmutable $now
    ) {}

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $interval): void
    {
        $this->now = $this->now->add(new \DateInterval($interval));
    }
}

// Usage in tests
$clock = new FakeClock(new \DateTimeImmutable('2024-01-01 12:00:00'));
$user = User::create($id, 'john', 'john@example.com', $clock->now());

$clock->advance('PT1H'); // advance 1 hour
$user->updateUsername('jane', $clock->now());
```

## Integration

Inject `ClockInterface` into your command handlers instead of calling `new \DateTimeImmutable()` directly. This allows you to control time in tests without mocking global functions.

```php
class CreateUserHandler
{
    public function __construct(
        private ClockInterface $clock
    ) {}

    public function __invoke(CreateUserCommand $command): User
    {
        return User::create(
            $command->id,
            $command->username,
            $command->email,
            $this->clock->now()
        );
    }
}
```
