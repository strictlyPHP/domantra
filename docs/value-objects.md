# Value Objects

Domantra provides two interfaces for building value objects.

## `ValueObject`

Base interface requiring an `equals()` method for structural equality:

```php
use StrictlyPHP\Domantra\ValueObject\ValueObject;

class Email implements ValueObject
{
    public function __construct(
        private readonly string $value
    ) {}

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->value === $other->value;
    }
}
```

## `StringValueObject`

Extends `ValueObject` with `Stringable` and `JsonSerializable`. Use this for identifiers — the `Stringable` implementation is required for cache key resolution and query bus lookups.

```php
use StrictlyPHP\Domantra\ValueObject\StringValueObject;

class UserId implements StringValueObject
{
    public function __construct(
        private readonly string $id
    ) {}

    public function __toString(): string
    {
        return $this->id;
    }

    public function jsonSerialize(): string
    {
        return $this->id;
    }

    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->id === $other->id;
    }
}
```

IDs used as query objects must implement `\Stringable` so the query bus can convert them to cache keys.
