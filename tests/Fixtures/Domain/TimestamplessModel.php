<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Fixtures\Domain;

use DateTimeInterface;

use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;

class TimestamplessModel extends AbstractAggregateRoot
{
    private UserId $id;

    private string $username;

    private string $email;

    public static function create(
        UserId $id,
        string $username,
        string $email,
        DateTimeInterface $createdAt,
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

    public function updateUsername(string $username, DateTimeInterface $happenedAt): void
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
        return new UserDto(
            $this->id,
            $this->username,
            $this->email
        );
    }
}
