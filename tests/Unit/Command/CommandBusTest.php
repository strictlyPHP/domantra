<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerInterface;
use StrictlyPHP\Domantra\Command\CommandBus;
use StrictlyPHP\Domantra\Command\CommandException;
use StrictlyPHP\Domantra\Command\CommandInterface;
use StrictlyPHP\Domantra\Command\EventBusInterface;
use StrictlyPHP\Domantra\Command\EventInterface;
use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;
use StrictlyPHP\Domantra\Domain\CachedDtoInterface;
use StrictlyPHP\Domantra\Domain\EventLogItem;

class CommandBusTest extends TestCase
{
    protected MockObject&LoggerInterface $logger;

    protected MockObject&EventBusInterface $eventBus;

    protected MockObject&DtoCacheHandlerInterface $cacheHandler;

    protected CommandBus $commandBus;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->eventBus = $this->createMock(EventBusInterface::class);
        $this->cacheHandler = $this->createMock(DtoCacheHandlerInterface::class);

        $this->commandBus = CommandBus::create($this->logger, $this->eventBus, $this->cacheHandler);
    }

    public function testRegisterHandlerWithInvalidParameterCountThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Handler must accept exactly one parameter');

        $this->commandBus->registerHandler(
            CommandInterface::class,
            function ($param1, $param2) {}
        );
    }

    public function testRegisterHandlerWithInvalidParameterTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Handler parameter must be an instance of CommandInterface');

        $this->commandBus->registerHandler(
            CommandInterface::class,
            function ($param) {}
        );
    }

    public function testDispatchWithUnregisteredCommandThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $mockCommand = $this->createMock(CommandInterface::class);
        $this->expectExceptionMessage(sprintf('No handler registered for command: %s', get_class($mockCommand)));

        $this->commandBus->dispatch($mockCommand);
    }

    public function testDispatchWithRegisteredCommandCallsHandler(): void
    {
        $mockCommand = $this->createMock(CommandInterface::class);
        $mockModel = $this->createMock(AbstractAggregateRoot::class);
        $mockDto = $this->createMock(CachedDtoInterface::class);


        $this->commandBus->registerHandler(
            get_class($mockCommand),
            new class($mockModel) implements CommandInterface {
                private AbstractAggregateRoot $mockModel;

                public function __construct(AbstractAggregateRoot $mockModel)
                {
                    $this->mockModel = $mockModel;
                }

                public function __invoke(CommandInterface $command): AbstractAggregateRoot
                {
                    return $this->mockModel;
                }
            }
        );

        $eventLogItem =
            new EventLogItem(
                name: 'fooBar',
                event: $this->createMock(EventInterface::class),
                happenedAt: new \DateTimeImmutable(),
                dto: (object) [
                    'id' => 'test-id',
                    'name' => 'Test Model',
                ]
            );

        $mockModel->expects($this->once())
            ->method('_getEventLogItems')
            ->willReturn([$eventLogItem]);

        $this->eventBus->expects($this->once())
            ->method('dispatch')
            ->with($eventLogItem);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Event dispatched', [
                'name' => 'fooBar',
                'id' => 'test-id',
            ]);

        $mockModel->expects($this->once())
            ->method('getDto')
            ->willReturn($mockDto);

        $this->cacheHandler->expects($this->once())
            ->method('set')
            ->with($mockDto);

        $this->commandBus->dispatch($mockCommand);
    }

    /**
     * @return array<string, array{mixed, ?string}>
     */
    public static function idProvider(): array
    {
        return [
            'stringable value object' => [new class() implements \Stringable {
                public function __toString(): string
                {
                    return 'placement-1';
                }
            }, 'placement-1'],
            'integer' => [42, '42'],
            'neither' => [['not', 'scalar'], null],
        ];
    }

    #[DataProvider('idProvider')]
    public function testEventDispatchedLogLeavesOutTheEventAndItsDtos(mixed $id, ?string $loggedId): void
    {
        $mockCommand = $this->createMock(CommandInterface::class);
        $mockModel = $this->createMock(AbstractAggregateRoot::class);
        $this->commandBus->registerHandler(
            get_class($mockCommand),
            function (CommandInterface $command) use ($mockModel) {
                return $mockModel;
            }
        );

        $mockModel->method('getDto')->willReturn($this->createMock(CachedDtoInterface::class));
        $mockModel->method('_getEventLogItems')->willReturn([
            new EventLogItem(
                name: 'placementDeclined',
                event: $this->createMock(EventInterface::class),
                happenedAt: new \DateTimeImmutable(),
                dto: (object) [
                    'id' => $id,
                    'reason' => 'Private reason',
                ],
                previousDto: (object) [
                    'email' => 'someone@example.com',
                ],
            ),
        ]);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Event dispatched', [
                'name' => 'placementDeclined',
                'id' => $loggedId,
            ]);

        $this->commandBus->dispatch($mockCommand);
    }

    public function testDispatchWithHandlerReturningInvalidModelThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('Response from handler must be an instance of %s or array of %s, got %s', AbstractAggregateRoot::class, AbstractAggregateRoot::class, \stdClass::class));

        $mockCommand = $this->createMock(CommandInterface::class);
        $this->commandBus->registerHandler(
            get_class($mockCommand),
            function (CommandInterface $command) {
                return new \stdClass(); // Not an AbstractAggregateRoot
            }
        );
        $this->commandBus->dispatch($mockCommand);
    }

    public function testDispatchWithNoEventsThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('No events to dispatch for command %s', get_class($this->createMock(CommandInterface::class))));

        $mockCommand = $this->createMock(CommandInterface::class);
        $mockModel = $this->createMock(AbstractAggregateRoot::class);

        $this->commandBus->registerHandler(
            get_class($mockCommand),
            function (CommandInterface $command) use ($mockModel) {
                return $mockModel; // No events
            }
        );

        $this->commandBus->dispatch($mockCommand);
    }

    public function testDispatchWithHandlerThrowingCommandException(): void
    {
        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('Handler threw an exception');

        $mockCommand = $this->createMock(CommandInterface::class);
        $mockModel = $this->createMock(AbstractAggregateRoot::class);
        $mockDto = $this->createMock(CachedDtoInterface::class);

        $this->commandBus->registerHandler(
            get_class($mockCommand),
            new class($mockModel) implements CommandInterface {
                private AbstractAggregateRoot $mockModel;

                public function __construct(AbstractAggregateRoot $mockModel)
                {
                    $this->mockModel = $mockModel;
                }

                public function __invoke(CommandInterface $command): AbstractAggregateRoot
                {
                    throw new CommandException('Handler threw an exception', 0, null, $this->mockModel);
                }
            }
        );

        $eventLogItem =
            new EventLogItem(
                name: 'fooBar',
                event: $this->createMock(EventInterface::class),
                happenedAt: new \DateTimeImmutable(),
                dto: (object) [
                    'id' => 'test-id',
                    'name' => 'Test Model',
                ]
            );

        $mockModel->expects($this->once())
            ->method('_getEventLogItems')
            ->willReturn([$eventLogItem]);

        $this->eventBus->expects($this->once())
            ->method('dispatch')
            ->with($eventLogItem);

        $mockModel->expects($this->once())
            ->method('getDto')
            ->willReturn($mockDto);

        $this->cacheHandler->expects($this->once())
            ->method('set')
            ->with($mockDto);

        $this->commandBus->dispatch($mockCommand);
    }
}
