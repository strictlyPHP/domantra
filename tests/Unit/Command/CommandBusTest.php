<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Command;

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
use StrictlyPHP\Domantra\Domain\EventLogItem;

class CommandBusTest extends TestCase
{
    protected MockObject & LoggerInterface $logger;

    protected MockObject & EventBusInterface $eventBus;

    protected MockObject & DtoCacheHandlerInterface $cacheHandler;

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

        $this->cacheHandler->expects($this->once())
            ->method('set')
            ->with($mockModel);

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

        $this->cacheHandler->expects($this->once())
            ->method('set')
            ->with($mockModel);

        $this->commandBus->dispatch($mockCommand);
    }
}
