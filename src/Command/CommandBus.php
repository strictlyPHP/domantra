<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Command;

use Psr\Log\LoggerInterface;
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
        $model = $this->handlers[$class]($command);
        if (! $model instanceof AbstractAggregateRoot) {
            throw new \RuntimeException(sprintf('Response from handler must be an instance of %s, got %s', AbstractAggregateRoot::class, get_class($model)));
        }

        $_events = $model->_getEventLogItems();
        if (empty($_events)) {
            throw new \RuntimeException(sprintf('No events to dispatch for command %s', $class));
        }
        foreach ($_events as $eventData) {
            $this->eventBus->dispatch($eventData);
            $this->logger->info('Event dispatched', [
                'eventData' => $eventData,
            ]);
        }
        $this->cacheHandler->set($model->jsonSerialize(), $model->getCacheKey(), get_class($model));
    }
}
