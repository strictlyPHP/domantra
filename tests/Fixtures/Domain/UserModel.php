<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Fixtures\Domain;

use DateTimeImmutable;

use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;
use StrictlyPHP\Domantra\Domain\CachedDtoInterface;
use StrictlyPHP\Domantra\Domain\UseTimestamps;

#[UseTimestamps(softDelete: true)]
class UserModel extends AbstractAggregateRoot
{
    private UserId $id;

    private string $username;

    private string $email;

    public static function create(
        UserId $id,
        string $username,
        string $email,
        DateTimeImmutable $createdAt,
    ): self {
        $model = new self();
        $model->recordAndApplyThat(
            new UserWasCreated($id, $username, $email),
            $createdAt
        );
        return $model;
    }

    protected function applyThatUserWasCreated(UserWasCreated $event): void
    {
        $this->id = $event->id;
        $this->username = $event->username;
        $this->email = $event->email;
    }

    public function updateUsername(string $username, DateTimeImmutable $happenedAt): void
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

    public function getCacheKey(): string
    {
        return (string) $this->id;
    }

    public function getId(): UserId
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getDto(): UserDto
    {
        return new UserDto(
            $this->id,
            $this->username,
            $this->email
        );
    }
}
