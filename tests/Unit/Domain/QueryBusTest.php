<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Domain;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use StrictlyPHP\Domantra\Domain\PaginatedIdCollection;
use StrictlyPHP\Domantra\Query\AggregateRootHandler;
use StrictlyPHP\Domantra\Query\CachedDtoHandler;
use StrictlyPHP\Domantra\Query\Exception\ItemNotFoundException;
use StrictlyPHP\Domantra\Query\Exception\ItemNotFoundExceptionInterface;
use StrictlyPHP\Domantra\Query\ExpansionPolicy;
use StrictlyPHP\Domantra\Query\Handlers\DtoHandlerHandlerInterface;
use StrictlyPHP\Domantra\Query\Handlers\PaginatedHandlerInterface;
use StrictlyPHP\Domantra\Query\Handlers\SingleHandlerInterface;
use StrictlyPHP\Domantra\Query\QueryBus;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\ProfileId;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\TeamId;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserId;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserQuery;

class QueryBusTest extends TestCase
{
    protected AggregateRootHandler & MockObject $aggregateRootHandler;

    protected CachedDtoHandler & MockObject $cachedDtoHandler;

    protected QueryBus $queryBus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aggregateRootHandler = $this->createMock(AggregateRootHandler::class);
        $this->cachedDtoHandler = $this->createMock(CachedDtoHandler::class);
        $this->queryBus = new QueryBus(
            $this->aggregateRootHandler,
            $this->cachedDtoHandler
        );
    }

    public function testHandleWithNoRegisteredHandler(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler registered for query: ' . UserId::class);

        $query = new UserId('test-id');
        $this->queryBus->handle($query);
    }

    public function testHandleWithUnStringableQuery(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query must implement Stringable when the return type is StrictlyPHP\Domantra\Domain\AbstractAggregateRoot');

        $query = new \stdClass(); // Not a Stringable object
        $handler = $this->createMock(SingleHandlerInterface::class);

        $this->queryBus->registerHandler(\stdClass::class, $handler);
        $this->queryBus->handle($query);
    }

    public function testExpandDtoWithInvalidHandlerType(): void
    {
        $queryBus = new class($this->aggregateRootHandler, $this->cachedDtoHandler) extends QueryBus {
            public function exposeExpandDto(object $dto): object
            {
                return $this->expandDto($dto, null);
            }
        };
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Handler %s must be an instance of %s or %s',
                'stdClass',
                'StrictlyPHP\\Domantra\\Query\\Handlers\\DtoHandlerHandlerInterface',
                'StrictlyPHP\\Domantra\\Query\\Handlers\\SingleHandlerInterface'
            )
        );

        // Create a mock handler that doesn't implement any of the required interfaces
        $invalidHandler = new class() {};

        // Register the invalid handler for stdClass
        $reflection = new \ReflectionClass(QueryBus::class);
        $handlersProperty = $reflection->getProperty('handlers');
        $handlersProperty->setAccessible(true);
        $handlers = $handlersProperty->getValue($queryBus);
        $handlers['stdClass'] = $invalidHandler;
        $handlersProperty->setValue($queryBus, $handlers);

        // Also set the expansion policy to ByDefault for stdClass so the
        // expansion branch is exercised (reaching the invalid-handler error).
        $expansionPolicyProperty = $reflection->getProperty('expansionPolicy');
        $expansionPolicyProperty->setAccessible(true);
        $expansionPolicy = $expansionPolicyProperty->getValue($queryBus);
        $expansionPolicy['stdClass'] = ExpansionPolicy::ByDefault;
        $expansionPolicyProperty->setValue($queryBus, $expansionPolicy);

        // Create a test DTO with a property that will use the invalid handler
        $dto = (object) [
            'test' => (object) [
                'id' => 'test',
            ], // This will be processed by the invalid handler
        ];

        // Call the protected method through our test class
        $queryBus->exposeExpandDto($dto);
    }

    public function testHandleWithRegisteredHandler(): void
    {
        $query = new UserId('test-id');
        $handler = $this->createMock(SingleHandlerInterface::class);
        $dto = (object) [
            'id' => $query,
            'name' => 'Test Name',
        ];

        $this->queryBus->registerHandler(UserId::class, $handler);

        $this->aggregateRootHandler->expects($this->once())
            ->method('handle')
            ->with($query, $handler)
            ->willReturn($dto);

        $response = $this->queryBus->handle($query);

        $expected = (object) [
            'item' => $dto,
        ];
        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testHandleWithCachedDtoHandler(): void
    {
        $userId = new UserId('test-id');
        $profileId = new ProfileId('profile-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $userDto = (object) [
            'id' => $userId,
            'name' => 'Test Name',
            'profile' => $profileId,
        ];

        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);
        $profileDto = (object) [
            'id' => $profileId,
            'bio' => 'Test Bio',
        ];

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler, ExpansionPolicy::ByDefault);

        $this->aggregateRootHandler->expects($this->once())
            ->method('handle')
            ->with($userId, $userHandler)
            ->willReturn($userDto);

        $this->cachedDtoHandler->expects($this->once())
            ->method('handle')
            ->with($profileId, $profileHandler)
            ->willReturn($profileDto);

        $response = $this->queryBus->handle($userId);

        $expandedDto = (object) [
            'id' => $userId,
            'name' => 'Test Name',
            'profile' => $profileId,
            'profileExpanded' => (object) [
                'id' => $profileId,
                'bio' => 'Test Bio',
            ],
        ];
        $expected = (object) [
            'item' => $expandedDto,
        ];
        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testHandleWithRegisteredPaginatedHandler(): void
    {
        $query = new UserQuery();
        $id = new UserId('test-id');
        $handler = $this->createMock(SingleHandlerInterface::class);
        $paginatedHandler = $this->createMock(PaginatedHandlerInterface::class);
        $dto = (object) [
            'id' => $id,
            'name' => 'Test Name',
        ];

        $this->queryBus->registerHandler(UserId::class, $handler);
        $this->queryBus->registerHandler(UserQuery::class, $paginatedHandler);

        $paginatedHandler->expects($this->once())
            ->method('__invoke')
            ->with($query)
            ->willReturn(new PaginatedIdCollection(ids: [$id], page: 1, perPage: 10, totalItems: 1));

        $this->aggregateRootHandler->expects($this->once())
            ->method('handle')
            ->with($id, $handler)
            ->willReturn($dto);

        $response = $this->queryBus->handle($query);

        $expected = (object) [
            'items' => [$dto],
            'page' => 1,
            'perPage' => 10,
            'totalItems' => 1,
        ];
        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testHandleExpandedWithRegisteredHandler(): void
    {
        $query = new UserId('test-id');
        $profileId = new ProfileId('profile-id');
        $handler1 = $this->createMock(SingleHandlerInterface::class);
        $handler2 = $this->createMock(SingleHandlerInterface::class);
        $dto = (object) [
            'id' => $query,
            'profile' => $profileId,
            'name' => 'Test Name',
        ];

        $profileDto = (object) [
            'id' => $profileId,
            'bio' => 'hello world',
        ];

        $expandedDto = (object) [
            'id' => $query,
            'profile' => $profileId,
            'profileExpanded' => $profileDto,
            'name' => 'Test Name',
        ];

        $this->queryBus->registerHandler(UserId::class, $handler1);
        $this->queryBus->registerHandler(ProfileId::class, $handler2, ExpansionPolicy::ByDefault);

        $expectedCalls = [
            [
                'args' => [$query, $handler1],
                'return' => $dto,
            ],
            [
                'args' => [$profileId, $handler2],
                'return' => $profileDto,
            ],
        ];

        $callCount = 0;

        $this->aggregateRootHandler->expects($this->exactly(2))
            ->method('handle')
            ->with($this->callback(function ($arg1) use (&$callCount, $expectedCalls) {
                // Adjust argument check depending on your method signature
                $expectedArgs = $expectedCalls[$callCount]['args'];
                $isValid = $arg1 === $expectedArgs[0]; // example check
                return $isValid;
            }))
            ->willReturnCallback(function () use (&$callCount, $expectedCalls) {
                $returnValue = $expectedCalls[$callCount]['return'];
                $callCount++;
                return $returnValue;
            });

        $response = $this->queryBus->handle($query);

        $expected = (object) [
            'item' => $expandedDto,
        ];
        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testExpandDtoStripsIdSuffixFromPropertyName(): void
    {
        $userId = new UserId('test-id');
        $profileId = new ProfileId('profile-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $userDto = (object) [
            'id' => $userId,
            'name' => 'Test Name',
            'profileId' => $profileId,
        ];

        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);
        $profileDto = (object) [
            'id' => $profileId,
            'bio' => 'Test Bio',
        ];

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler, ExpansionPolicy::ByDefault);

        $this->aggregateRootHandler->expects($this->once())
            ->method('handle')
            ->with($userId, $userHandler)
            ->willReturn($userDto);

        $this->cachedDtoHandler->expects($this->once())
            ->method('handle')
            ->with($profileId, $profileHandler)
            ->willReturn($profileDto);

        $response = $this->queryBus->handle($userId);

        $expandedDto = (object) [
            'id' => $userId,
            'name' => 'Test Name',
            'profileId' => $profileId,
            'profile' => (object) [
                'id' => $profileId,
                'bio' => 'Test Bio',
            ],
        ];
        $expected = (object) [
            'item' => $expandedDto,
        ];
        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testExpandDtoSetsNullWhenItemNotFound(): void
    {
        $userId = new UserId('test-id');
        $profileId = new ProfileId('profile-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $userDto = (object) [
            'id' => $userId,
            'name' => 'Test Name',
            'profileId' => $profileId,
        ];

        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler, ExpansionPolicy::ByDefault);

        $this->aggregateRootHandler->expects($this->once())
            ->method('handle')
            ->with($userId, $userHandler)
            ->willReturn($userDto);

        $this->cachedDtoHandler->expects($this->once())
            ->method('handle')
            ->with($profileId, $profileHandler)
            ->willThrowException(new ItemNotFoundException('Not found'));

        $response = $this->queryBus->handle($userId);

        $expandedDto = (object) [
            'id' => $userId,
            'name' => 'Test Name',
            'profileId' => $profileId,
            'profile' => null,
        ];
        $expected = (object) [
            'item' => $expandedDto,
        ];
        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testExpandDtoSetsNullWhenConsumerExceptionImplementsMarkerInterface(): void
    {
        $userId = new UserId('test-id');
        $profileId = new ProfileId('profile-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $userDto = (object) [
            'id' => $userId,
            'name' => 'Test Name',
            'profileId' => $profileId,
        ];

        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler, ExpansionPolicy::ByDefault);

        $this->aggregateRootHandler->expects($this->once())
            ->method('handle')
            ->with($userId, $userHandler)
            ->willReturn($userDto);

        $consumerException = new class('consumer 404') extends \RuntimeException implements ItemNotFoundExceptionInterface {
        };

        $this->cachedDtoHandler->expects($this->once())
            ->method('handle')
            ->with($profileId, $profileHandler)
            ->willThrowException($consumerException);

        $response = $this->queryBus->handle($userId);

        $expandedDto = (object) [
            'id' => $userId,
            'name' => 'Test Name',
            'profileId' => $profileId,
            'profile' => null,
        ];
        $expected = (object) [
            'item' => $expandedDto,
        ];
        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testExpandDtoBubblesUnrelatedExceptions(): void
    {
        $userId = new UserId('test-id');
        $profileId = new ProfileId('profile-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $userDto = (object) [
            'id' => $userId,
            'name' => 'Test Name',
            'profileId' => $profileId,
        ];

        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler, ExpansionPolicy::ByDefault);

        $this->aggregateRootHandler->expects($this->once())
            ->method('handle')
            ->with($userId, $userHandler)
            ->willReturn($userDto);

        $this->cachedDtoHandler->expects($this->once())
            ->method('handle')
            ->with($profileId, $profileHandler)
            ->willThrowException(new \RuntimeException('boom'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->queryBus->handle($userId);
    }

    public function testHandleWithNullExpandListExpandsEveryEligibleProperty(): void
    {
        $userId = new UserId('test-id');
        $profileId = new ProfileId('profile-id');
        $teamId = new TeamId('team-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);
        $teamHandler = $this->createMock(DtoHandlerHandlerInterface::class);

        $userDto = (object) [
            'id' => $userId,
            'profileId' => $profileId,
            'teamId' => $teamId,
        ];
        $profileDto = (object) [
            'id' => $profileId,
            'bio' => 'bio',
        ];
        $teamDto = (object) [
            'id' => $teamId,
            'name' => 'team',
        ];

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler, ExpansionPolicy::ByDefault);
        $this->queryBus->registerHandler(TeamId::class, $teamHandler, ExpansionPolicy::ByDefault);

        $this->aggregateRootHandler->expects($this->once())->method('handle')->with($userId, $userHandler)->willReturn($userDto);
        $this->cachedDtoHandler->expects($this->exactly(2))->method('handle')->willReturnCallback(
            fn ($id) => $id === $profileId ? $profileDto : $teamDto
        );

        $response = $this->queryBus->handle($userId, null, null);

        $expected = (object) [
            'item' => (object) [
                'id' => $userId,
                'profileId' => $profileId,
                'profile' => $profileDto,
                'teamId' => $teamId,
                'team' => $teamDto,
            ],
        ];
        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testHandleWithNullExpandListSkipsHandlerRegisteredWithOnRequestPolicy(): void
    {
        $userId = new UserId('test-id');
        $profileId = new ProfileId('profile-id');
        $teamId = new TeamId('team-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);
        $teamHandler = $this->createMock(DtoHandlerHandlerInterface::class);

        $userDto = (object) [
            'id' => $userId,
            'profileId' => $profileId,
            'teamId' => $teamId,
        ];
        $profileDto = (object) [
            'id' => $profileId,
            'bio' => 'bio',
        ];

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler, ExpansionPolicy::ByDefault);
        $this->queryBus->registerHandler(TeamId::class, $teamHandler, ExpansionPolicy::OnRequest);

        $this->aggregateRootHandler->expects($this->once())->method('handle')->with($userId, $userHandler)->willReturn($userDto);
        $this->cachedDtoHandler->expects($this->once())
            ->method('handle')
            ->with($profileId, $profileHandler)
            ->willReturn($profileDto);

        $response = $this->queryBus->handle($userId, null, null);

        $responseItem = $response->jsonSerialize()->item;
        $this->assertSame($profileDto, $responseItem->profile);
        $this->assertSame($teamId, $responseItem->teamId);
        $this->assertFalse(property_exists($responseItem, 'team'), 'ExpansionPolicy::OnRequest must not auto-expand on null expand list');
    }

    public function testHandleWithExplicitExpandListExpandsHandlerRegisteredWithOnRequestPolicy(): void
    {
        $userId = new UserId('test-id');
        $teamId = new TeamId('team-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $teamHandler = $this->createMock(DtoHandlerHandlerInterface::class);

        $userDto = (object) [
            'id' => $userId,
            'teamId' => $teamId,
        ];
        $teamDto = (object) [
            'id' => $teamId,
            'name' => 'team',
        ];

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(TeamId::class, $teamHandler, ExpansionPolicy::OnRequest);

        $this->aggregateRootHandler->expects($this->once())->method('handle')->with($userId, $userHandler)->willReturn($userDto);
        $this->cachedDtoHandler->expects($this->once())
            ->method('handle')
            ->with($teamId, $teamHandler)
            ->willReturn($teamDto);

        $response = $this->queryBus->handle($userId, null, ['teamId']);

        $responseItem = $response->jsonSerialize()->item;
        $this->assertSame($teamDto, $responseItem->team);
    }

    public function testHandleWithEmptyExpandListExpandsNothing(): void
    {
        $userId = new UserId('test-id');
        $profileId = new ProfileId('profile-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);

        $userDto = (object) [
            'id' => $userId,
            'profileId' => $profileId,
        ];

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler, ExpansionPolicy::ByDefault);

        $this->aggregateRootHandler->expects($this->once())->method('handle')->with($userId, $userHandler)->willReturn($userDto);
        $this->cachedDtoHandler->expects($this->never())->method('handle');

        $response = $this->queryBus->handle($userId, null, []);

        $expected = (object) [
            'item' => (object) [
                'id' => $userId,
                'profileId' => $profileId,
            ],
        ];
        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testHandleWithExpandListExpandsOnlyNamedProperty(): void
    {
        $userId = new UserId('test-id');
        $profileId = new ProfileId('profile-id');
        $teamId = new TeamId('team-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);
        $teamHandler = $this->createMock(DtoHandlerHandlerInterface::class);

        $userDto = (object) [
            'id' => $userId,
            'profileId' => $profileId,
            'teamId' => $teamId,
        ];
        $profileDto = (object) [
            'id' => $profileId,
            'bio' => 'bio',
        ];

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler, ExpansionPolicy::ByDefault);
        $this->queryBus->registerHandler(TeamId::class, $teamHandler, ExpansionPolicy::ByDefault);

        $this->aggregateRootHandler->expects($this->once())->method('handle')->with($userId, $userHandler)->willReturn($userDto);
        $this->cachedDtoHandler->expects($this->once())
            ->method('handle')
            ->with($profileId, $profileHandler)
            ->willReturn($profileDto);

        $response = $this->queryBus->handle($userId, null, ['profileId']);

        $responseItem = $response->jsonSerialize()->item;
        $this->assertSame($profileDto, $responseItem->profile);
        $this->assertSame($teamId, $responseItem->teamId);
        $this->assertFalse(property_exists($responseItem, 'team'), 'team should not be expanded when not in the expand list');
    }

    public function testHandleWithExpandListIgnoresUnknownNames(): void
    {
        $userId = new UserId('test-id');
        $profileId = new ProfileId('profile-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);

        $userDto = (object) [
            'id' => $userId,
            'profileId' => $profileId,
        ];

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler, ExpansionPolicy::ByDefault);

        $this->aggregateRootHandler->expects($this->once())->method('handle')->with($userId, $userHandler)->willReturn($userDto);
        $this->cachedDtoHandler->expects($this->never())->method('handle');

        $response = $this->queryBus->handle($userId, null, ['doesNotExist']);

        $responseItem = $response->jsonSerialize()->item;
        $this->assertFalse(property_exists($responseItem, 'profile'));
        $this->assertSame($profileId, $responseItem->profileId);
    }

    public function testHandleWithExpandListStillHonoursExpansionPolicyDisabled(): void
    {
        $userId = new UserId('test-id');
        $profileId = new ProfileId('profile-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);

        $userDto = (object) [
            'id' => $userId,
            'profileId' => $profileId,
        ];

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler, ExpansionPolicy::Disabled);

        $this->aggregateRootHandler->expects($this->once())->method('handle')->with($userId, $userHandler)->willReturn($userDto);
        $this->cachedDtoHandler->expects($this->never())->method('handle');

        $response = $this->queryBus->handle($userId, null, ['profileId']);

        $responseItem = $response->jsonSerialize()->item;
        $this->assertFalse(property_exists($responseItem, 'profile'), 'ExpansionPolicy::Disabled must not be overridden by expand list');
    }

    public function testHandleWithExpandListAppliesToEveryItemInPaginatedResponse(): void
    {
        $query = new UserQuery();
        $userIdA = new UserId('user-a');
        $userIdB = new UserId('user-b');
        $profileIdA = new ProfileId('profile-a');
        $profileIdB = new ProfileId('profile-b');
        $teamIdA = new TeamId('team-a');
        $teamIdB = new TeamId('team-b');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $paginatedHandler = $this->createMock(PaginatedHandlerInterface::class);
        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);
        $teamHandler = $this->createMock(DtoHandlerHandlerInterface::class);

        $userDtoA = (object) [
            'id' => $userIdA,
            'profileId' => $profileIdA,
            'teamId' => $teamIdA,
        ];
        $userDtoB = (object) [
            'id' => $userIdB,
            'profileId' => $profileIdB,
            'teamId' => $teamIdB,
        ];
        $profileDtoA = (object) [
            'id' => $profileIdA,
        ];
        $profileDtoB = (object) [
            'id' => $profileIdB,
        ];

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(UserQuery::class, $paginatedHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler, ExpansionPolicy::ByDefault);
        $this->queryBus->registerHandler(TeamId::class, $teamHandler, ExpansionPolicy::ByDefault);

        $paginatedHandler->expects($this->once())
            ->method('__invoke')
            ->with($query)
            ->willReturn(new PaginatedIdCollection(ids: [$userIdA, $userIdB], page: 1, perPage: 10, totalItems: 2));

        $this->aggregateRootHandler->expects($this->exactly(2))
            ->method('handle')
            ->willReturnCallback(fn ($id) => $id === $userIdA ? $userDtoA : $userDtoB);

        $this->cachedDtoHandler->expects($this->exactly(2))
            ->method('handle')
            ->willReturnCallback(fn ($id) => $id === $profileIdA ? $profileDtoA : $profileDtoB);

        $response = $this->queryBus->handle($query, null, ['profileId']);

        $items = $response->jsonSerialize()->items;
        $this->assertCount(2, $items);

        // Each item gets its own profile DTO — guards against a regression that
        // maps the same expansion result to every paginated item.
        $this->assertSame($profileIdA, $items[0]->profileId);
        $this->assertSame($profileDtoA, $items[0]->profile);
        $this->assertSame($profileIdB, $items[1]->profileId);
        $this->assertSame($profileDtoB, $items[1]->profile);

        foreach ($items as $item) {
            $this->assertFalse(property_exists($item, 'team'), 'team should not be expanded on any paginated item');
        }
    }

    public function testHandleWithExpandListMatchesOnOriginalPropertyNameForNonIdField(): void
    {
        $userId = new UserId('test-id');
        $teamId = new TeamId('team-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $teamHandler = $this->createMock(DtoHandlerHandlerInterface::class);

        $userDto = (object) [
            'id' => $userId,
            'team' => $teamId,
        ];
        $teamDto = (object) [
            'id' => $teamId,
            'name' => 'team',
        ];

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(TeamId::class, $teamHandler, ExpansionPolicy::ByDefault);

        $this->aggregateRootHandler->expects($this->once())->method('handle')->with($userId, $userHandler)->willReturn($userDto);
        $this->cachedDtoHandler->expects($this->once())->method('handle')->with($teamId, $teamHandler)->willReturn($teamDto);

        $response = $this->queryBus->handle($userId, null, ['team']);

        $responseItem = $response->jsonSerialize()->item;
        $this->assertSame($teamDto, $responseItem->teamExpanded);
    }

    public function testHandleWithExpandListDoesNotMatchOnExpandedOutputName(): void
    {
        $userId = new UserId('test-id');
        $profileId = new ProfileId('profile-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);

        $userDto = (object) [
            'id' => $userId,
            'profileId' => $profileId,
        ];

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler, ExpansionPolicy::ByDefault);

        $this->aggregateRootHandler->expects($this->once())->method('handle')->with($userId, $userHandler)->willReturn($userDto);
        $this->cachedDtoHandler->expects($this->never())->method('handle');

        // Passing the *expanded* output key ("profile") must not expand the source field ("profileId").
        $response = $this->queryBus->handle($userId, null, ['profile']);

        $responseItem = $response->jsonSerialize()->item;
        $this->assertFalse(property_exists($responseItem, 'profile'));
        // The source field must survive a filter reject — guards against a regression
        // that wipes the original property when the expand list doesn't match.
        $this->assertSame($profileId, $responseItem->profileId);
    }

    public function testHandleWithExpandListSilentlySkipsNullValuedProperty(): void
    {
        $userId = new UserId('test-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);

        $userDto = (object) [
            'id' => $userId,
            'profileId' => null,
        ];

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler, ExpansionPolicy::ByDefault);

        $this->aggregateRootHandler->expects($this->once())->method('handle')->with($userId, $userHandler)->willReturn($userDto);
        $this->cachedDtoHandler->expects($this->never())->method('handle');

        $response = $this->queryBus->handle($userId, null, ['profileId']);

        $responseItem = $response->jsonSerialize()->item;
        $this->assertNull($responseItem->profileId);
        $this->assertFalse(property_exists($responseItem, 'profile'), 'null value must not be expanded even when named in the list');
    }

    public function testHandleWithExpandListContainingIdIsSilentNoOp(): void
    {
        $userId = new UserId('test-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);

        $userDto = (object) [
            'id' => $userId,
        ];

        // Registered with ExpansionPolicy::ByDefault so that a regression deleting
        // the `$property === 'id'` guard in expandDto is not absorbed by the
        // authorization check; only the id guard prevents a second handle() call.
        $this->queryBus->registerHandler(UserId::class, $userHandler, ExpansionPolicy::ByDefault);

        // Exactly one invocation: the top-level handle(). A second invocation
        // would mean the `id` field was re-resolved through expansion.
        $this->aggregateRootHandler->expects($this->once())
            ->method('handle')
            ->with($userId, $userHandler)
            ->willReturn($userDto);

        $response = $this->queryBus->handle($userId, null, ['id']);

        $responseItem = $response->jsonSerialize()->item;
        $this->assertSame($userId, $responseItem->id);
        $this->assertFalse(property_exists($responseItem, 'idExpanded'));
    }

    public function testExpansionResultSurvivesRawPropertyWithCollidingName(): void
    {
        $userId = new UserId('test-id');
        $profileId = new ProfileId('profile-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);
        $profileDto = (object) [
            'id' => $profileId,
            'bio' => 'bio',
        ];

        // DTO carries both `profileId` (the id reference) and a raw `profile`
        // field pointing at the same ProfileId. The expansion of `profileId`
        // derives the key `profile`, which collides with the raw field. Raw
        // fields must win deterministically — the expansion of `profile`
        // itself still produces `profileExpanded`.
        $userDto = (object) [
            'id' => $userId,
            'profileId' => $profileId,
            'profile' => $profileId,
        ];

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler, ExpansionPolicy::ByDefault);

        $this->aggregateRootHandler->expects($this->once())
            ->method('handle')
            ->with($userId, $userHandler)
            ->willReturn($userDto);

        // Called exactly once: expansion for `profileId` is short-circuited
        // because the derived key `profile` already exists as a raw property.
        // Expansion for the raw `profile` field still runs (→ profileExpanded).
        $this->cachedDtoHandler->expects($this->once())
            ->method('handle')
            ->with($profileId, $profileHandler)
            ->willReturn($profileDto);

        $response = $this->queryBus->handle($userId);

        $responseItem = $response->jsonSerialize()->item;
        $this->assertSame($profileId, $responseItem->profile, 'raw `profile` field must not be overwritten by expansion of `profileId`');
        $this->assertSame($profileDto, $responseItem->profileExpanded);
    }
}
