<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Command;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerInMemory;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerInterface;
use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;

class CommandBus implements CommandBusInterface
{
    /**
     * @var array<class-string, callable>
     */
    private array $handlers = [];

    public function __construct(
        private LoggerInterface $logger,
        private EventBusInterface $eventBus,
        private DtoCacheHandlerInterface $cacheHandler,
    ) {
    }

    public static function create(
        LoggerInterface $logger = null,
        EventBusInterface $eventBus = null,
        DtoCacheHandlerInterface $cacheHandler = null,
    ): self {
        return new self(
            $logger ?? new NullLogger(),
            $eventBus ?? new EventBusMock(),
            $cacheHandler ?? new DtoCacheHandlerInMemory()
        );
    }

    /**
     * @param class-string $commandClass
     */
    public function registerHandler(string $commandClass, callable $handler): void
    {
        $reflection = new \ReflectionFunction(\Closure::fromCallable($handler));
        $parameters = $reflection->getParameters();
        if (count($parameters) !== 1) {
            throw new \InvalidArgumentException('Handler must accept exactly one parameter');
        }
        $parameterType = $parameters[0]->getType();
        if (! $parameterType instanceof \ReflectionNamedType
            || ! is_a($parameterType->getName(), CommandInterface::class, true)) {
            throw new \InvalidArgumentException('Handler parameter must be an instance of CommandInterface');
        }
        $this->handlers[$commandClass] = $handler;
    }

    public function dispatch(CommandInterface $command): void
    {
        $class = get_class($command);
        if (! isset($this->handlers[$class])) {
            throw new \RuntimeException("No handler registered for command: $class");
        }

        $throwOnCompletion = null;
        try {
            $result = $this->handlers[$class]($command);
            if ($result === null) {
                return;
            }
        } catch (CommandException $e) {
            $throwOnCompletion = $e;
            $result = $e->model;
        }

        $aggregates = is_array($result) ? $result : [$result];

        $hasEvents = false;
        foreach ($aggregates as $aggregate) {
            if (! $aggregate instanceof AbstractAggregateRoot) {
                throw new \RuntimeException(
                    sprintf(
                        'Response from handler must be an instance of %s or array of %s, got %s',
                        AbstractAggregateRoot::class,
                        AbstractAggregateRoot::class,
                        get_class($aggregate)
                    ),
                    0,
                    $throwOnCompletion
                );
            }

            $_events = $aggregate->_getEventLogItems();
            if (! empty($_events)) {
                $hasEvents = true;
                foreach ($_events as $eventData) {
                    $this->eventBus->dispatch($eventData);
                    $this->logger->info('Event dispatched', [
                        'eventData' => $eventData,
                    ]);
                }
            }

            $this->cacheHandler->set($aggregate->getDto());
        }

        if (! $hasEvents) {
            throw new \RuntimeException(
                sprintf('No events to dispatch for command %s', $class),
                0,
                $throwOnCompletion
            );
        }

        if ($throwOnCompletion) {
            throw $throwOnCompletion;
        }
    }
}
