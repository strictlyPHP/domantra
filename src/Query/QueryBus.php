<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query;

use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;
use StrictlyPHP\Domantra\Domain\PaginatedIdCollection;

class QueryBus implements QueryBusInterface
{
    /**
     * @var array<class-string, callable>
     */
    private array $handlers = [];

    public function __construct(
        private AggregateRootHandler $aggregateRootHandler,
    ) {
    }

    /**
     * @param class-string $queryClass
     */
    public function registerHandler(string $queryClass, callable $handler): void
    {
        $reflection = new \ReflectionFunction(\Closure::fromCallable($handler));
        $parameters = $reflection->getParameters();

        if (count($parameters) !== 1) {
            throw new \InvalidArgumentException('Handler must accept exactly one parameter');
        }

        $returnType = $reflection->getReturnType();

        if ($returnType instanceof \ReflectionNamedType && ! $returnType->isBuiltin()) {
            $typeName = $returnType->getName();
            if (
                ! is_a($typeName, AbstractAggregateRoot::class, true)
                && ! is_a($typeName, PaginatedIdCollection::class, true)
            ) {
                throw new \RuntimeException(sprintf('Registration failed. Declared return type %s must be an instance of %s or %s', $typeName, AbstractAggregateRoot::class, PaginatedIdCollection::class));
            }
        }

        $this->handlers[$queryClass] = $handler;
    }

    public function handle(object $query): ResponseInterface
    {
        $class = get_class($query);
        if (! isset($this->handlers[$class])) {
            throw new \RuntimeException("No handler registered for query: $class");
        }
        $handler = $this->handlers[$class];
        $reflection = new \ReflectionFunction(\Closure::fromCallable($handler));

        $returnType = $reflection->getReturnType();
        if (! ($returnType instanceof \ReflectionNamedType)) {
            throw new \RuntimeException('Handler must return a named type');
        }
        $typeName = $returnType->getName();
        if (is_a($typeName, AbstractAggregateRoot::class, true)) {
            if ($query instanceof \Stringable) {
                return new ModelResponse(
                    $this->expandDto(
                        $this->aggregateRootHandler->handle($handler, $query)
                    )
                );
            } else {
                throw new \RuntimeException(sprintf('Query must implement %s when the return type is %s', \Stringable::class, AbstractAggregateRoot::class));
            }
        } elseif (is_a($typeName, PaginatedIdCollection::class, true)) {
            $paginatedCollection = $handler($query);
            $items = [];
            foreach ($paginatedCollection as $id) {
                $idClass = get_class($id);
                $idHandler = $this->handlers[$idClass];
                $items[] = $this->expandDto(
                    $this->aggregateRootHandler->handle($idHandler, $id)
                );
            }

            return new PaginatedModelResponse(
                $items,
                $paginatedCollection->getPage(),
                $paginatedCollection->getPerPage(),
                $paginatedCollection->getTotalItems()
            );
        } else {
            throw new \RuntimeException(sprintf('Handling failed. Declared return type %s must be an instance of %s or %s', $typeName, AbstractAggregateRoot::class, PaginatedIdCollection::class));
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
                    $value = $this->aggregateRootHandler->handle($handler, $value);
                }
            }
            $expanded->$property = $value;
        }

        return $expanded;
    }
}
