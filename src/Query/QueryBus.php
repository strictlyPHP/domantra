<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query;

use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;
use StrictlyPHP\Domantra\Query\Exception\ItemNotFoundException;
use StrictlyPHP\Domantra\Query\Handlers\DtoHandlerHandlerInterface;
use StrictlyPHP\Domantra\Query\Handlers\PaginatedHandlerInterface;
use StrictlyPHP\Domantra\Query\Handlers\SingleHandlerInterface;
use StrictlyPHP\Domantra\Query\Response\ModelResponse;
use StrictlyPHP\Domantra\Query\Response\PaginatedModelResponse;
use StrictlyPHP\Domantra\Query\Response\ResponseInterface;

class QueryBus implements QueryBusInterface
{
    /**
     * @var array<class-string, SingleHandlerInterface|PaginatedHandlerInterface<mixed>>
     */
    private array $handlers = [];

    public function __construct(
        private AggregateRootHandler $aggregateRootHandler,
        private CachedDtoHandler $cachedDtoHandler
    ) {
    }

    /**
     * @param class-string $queryClass
     */
    public function registerHandler(string $queryClass, SingleHandlerInterface|PaginatedHandlerInterface|DtoHandlerHandlerInterface $handler): void
    {
        $this->handlers[$queryClass] = $handler;
    }

    /**
     * @throws ItemNotFoundException
     */
    public function handle(object $query): ResponseInterface
    {
        $class = get_class($query);
        if (! isset($this->handlers[$class])) {
            throw new \RuntimeException("No handler registered for query: $class");
        }
        $handler = $this->handlers[$class];

        if ($handler instanceof SingleHandlerInterface) {
            if ($query instanceof \Stringable) {
                return new ModelResponse($this->expandDto($this->aggregateRootHandler->handle($query, $handler)));
            } else {
                throw new \RuntimeException(sprintf('Query must implement %s when the return type is %s', \Stringable::class, AbstractAggregateRoot::class));
            }
        } elseif ($handler instanceof PaginatedHandlerInterface) {
            $paginatedCollection = $handler->__invoke($query);
            $items = [];
            foreach ($paginatedCollection as $id) {
                $idClass = get_class($id);
                /** @var SingleHandlerInterface $idHandler */
                $idHandler = $this->handlers[$idClass];
                $items[] = $this->expandDto(
                    $this->aggregateRootHandler->handle($id, $idHandler)
                );
            }

            return new PaginatedModelResponse(
                $items,
                $paginatedCollection->getPage(),
                $paginatedCollection->getPerPage(),
                $paginatedCollection->getTotalItems()
            );
        } else {
            // We should never reach here. We are doing this to future-proof the code
            throw new \RuntimeException(sprintf('Handling failed. handler %s must be an instance of %s or %s', get_class($handler), SingleHandlerInterface::class, PaginatedHandlerInterface::class));
        }
    }

    protected function expandDto(object $dto): object
    {
        $expanded = (object) [];

        foreach (get_object_vars($dto) as $property => $value) {
            if (is_object($value) && $property !== 'id') {
                $class = get_class($value);
                if (isset($this->handlers[$class])) {
                    $handler = $this->handlers[$class];
                    try {
                        if ($handler instanceof DtoHandlerHandlerInterface) {
                            $value = $this->cachedDtoHandler->handle($value, $handler);
                        } elseif ($handler instanceof SingleHandlerInterface) {
                            $value = $this->aggregateRootHandler->handle($value, $handler);
                        } else {
                            throw new \RuntimeException(sprintf('Handler %s must be an instance of %s or %s', $class, DtoHandlerHandlerInterface::class, SingleHandlerInterface::class));
                        }
                    } catch (ItemNotFoundException $e) {
                        $value = null;
                    }
                }
            }
            $expanded->$property = $value;
        }

        return $expanded;
    }
}
