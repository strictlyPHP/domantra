<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Domain;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserModel;

class AbstractAggregateRootTest extends TestCase
{
    public function testRecordAndApplyThatCreates(): void
    {
        $now = new \DateTimeImmutable('2025-08-02 18:59:49.467350');
        $model = UserModel::create(
            id: 1,
            username: 'testuser',
            email: 'test@example.com',
            createdAt: $now
        );

        self::assertEquals(1, $model->getId());
        self::assertEquals('testuser', $model->getUsername());
        self::assertEquals('test@example.com', $model->getEmail());
        self::assertEquals($now, $model->getCreatedAt());
        self::assertNull($model->getUpdatedAt());
        self::assertNull($model->getDeletedAt());

        $events = [[
            'event' => [
                'id' => 1,
                'username' => 'testuser',
                'email' => 'test@example.com',
            ],
            'happenedAt' => [
                'date' => '2025-08-02 18:59:49.467350',
                'timezone_type' => 3,
                'timezone' => 'UTC',
            ],
            'dto' => [
                'id' => 1,
                'username' => 'testuser',
                'email' => 'test@example.com',
            ],
        ], ];
        self::assertEquals($events, json_decode(json_encode($model->_getEventLogItems()), true));
    }

    public function testRecordAndApplyThatUpdates(): void
    {
        $now = new \DateTimeImmutable('2025-08-02 18:59:49.467350');
        $model = UserModel::create(
            id: 1,
            username: 'testuser',
            email: 'test@example.com',
            createdAt: $now
        );
        self::assertEquals('testuser', $model->getUsername());
        self::assertEquals($now, $model->getCreatedAt());
        self::assertNull($model->getUpdatedAt());
        self::assertNull($model->getDeletedAt());

        $later = $now->modify('+1 hour');
        $model->updateUsername('updateduser', $later);

        self::assertEquals('updateduser', $model->getUsername());
        self::assertEquals($now, $model->getCreatedAt());
        self::assertEquals($later, $model->getUpdatedAt());
        self::assertNull($model->getDeletedAt());

        $events = [
            [
                'event' => [
                    'id' => 1,
                    'username' => 'testuser',
                    'email' => 'test@example.com',
                ],
                'happenedAt' => [
                    'date' => '2025-08-02 18:59:49.467350',
                    'timezone_type' => 3,
                    'timezone' => 'UTC',
                ],
                'dto' => [
                    'id' => 1,
                    'username' => 'testuser',
                    'email' => 'test@example.com',
                ],
            ], [
                'event' => [
                    'username' => 'updateduser',
                ],
                'happenedAt' => [
                    'date' => '2025-08-02 19:59:49.467350',
                    'timezone_type' => 3,
                    'timezone' => 'UTC',
                ],
                'dto' => [
                    'id' => 1,
                    'username' => 'updateduser',
                    'email' => 'test@example.com',
                ],
            ],
        ];
        self::assertEquals($events, json_decode(json_encode($model->_getEventLogItems()), true));
    }
}
