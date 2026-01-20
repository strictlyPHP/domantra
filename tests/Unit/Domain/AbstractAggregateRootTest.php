<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Domain;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserId;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserModel;

class AbstractAggregateRootTest extends TestCase
{
    public function testRecordAndApplyThatCreates(): void
    {
        $now = new \DateTimeImmutable('2025-08-02 18:59:49.467350');
        $id = new UserId('78b52d55-0d03-4c6c-a3e6-4bda7d908d32');
        $model = UserModel::create(
            id: $id,
            username: 'testuser',
            email: 'test@example.com',
            createdAt: $now
        );

        $this->assertEquals($id, $model->getId());
        $this->assertEquals('testuser', $model->getUsername());
        $this->assertEquals('test@example.com', $model->getEmail());
        $this->assertEquals($now, $model->getCreatedAt());
        $this->assertNull($model->getUpdatedAt());
        $this->assertNull($model->getDeletedAt());

        $events = [[
            'name' => 'strictlyPHP.tests.domantra.fixtures.domain.userWasCreated',
            'event' => [
                'id' => '78b52d55-0d03-4c6c-a3e6-4bda7d908d32',
                'username' => 'testuser',
                'email' => 'test@example.com',
            ],
            'happenedAt' => [
                'date' => '2025-08-02 18:59:49.467350',
                'timezone_type' => 3,
                'timezone' => 'UTC',
            ],
            'dto' => [
                'id' => '78b52d55-0d03-4c6c-a3e6-4bda7d908d32',
                'username' => 'testuser',
                'email' => 'test@example.com',
            ],
        ], ];
        $this->assertEquals($events, json_decode(json_encode($model->_getEventLogItems()), true));
    }

    public function testRecordAndApplyThatUpdates(): void
    {
        $id = new UserId('78b52d55-0d03-4c6c-a3e6-4bda7d908d32');
        $now = new \DateTimeImmutable('2025-08-02 18:59:49.467350');
        $model = UserModel::create(
            id: $id,
            username: 'testuser',
            email: 'test@example.com',
            createdAt: $now
        );
        $this->assertEquals('testuser', $model->getUsername());
        $this->assertEquals($now, $model->getCreatedAt());
        $this->assertNull($model->getUpdatedAt());
        $this->assertNull($model->getDeletedAt());

        $later = $now->modify('+1 hour');
        $model->updateUsername('updateduser', $later);

        $this->assertEquals('updateduser', $model->getUsername());
        $this->assertEquals($now, $model->getCreatedAt());
        $this->assertEquals($later, $model->getUpdatedAt());
        $this->assertNull($model->getDeletedAt());

        $events = [
            [
                'name' => 'strictlyPHP.tests.domantra.fixtures.domain.userWasCreated',
                'event' => [
                    'id' => '78b52d55-0d03-4c6c-a3e6-4bda7d908d32',
                    'username' => 'testuser',
                    'email' => 'test@example.com',
                ],
                'happenedAt' => [
                    'date' => '2025-08-02 18:59:49.467350',
                    'timezone_type' => 3,
                    'timezone' => 'UTC',
                ],
                'dto' => [
                    'id' => '78b52d55-0d03-4c6c-a3e6-4bda7d908d32',
                    'username' => 'testuser',
                    'email' => 'test@example.com',
                ],
            ], [
                'name' => 'strictlyPHP.tests.domantra.fixtures.domain.usernameWasUpdated',
                'event' => [
                    'username' => 'updateduser',
                ],
                'happenedAt' => [
                    'date' => '2025-08-02 19:59:49.467350',
                    'timezone_type' => 3,
                    'timezone' => 'UTC',
                ],
                'dto' => [
                    'id' => '78b52d55-0d03-4c6c-a3e6-4bda7d908d32',
                    'username' => 'updateduser',
                    'email' => 'test@example.com',
                ],
            ],
        ];
        $this->assertEquals($events, json_decode(json_encode($model->_getEventLogItems()), true));
    }
}
