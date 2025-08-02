<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query;

use StrictlyPHP\Domantra\Cache\DtoCacheHandlerInterface;
use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;

class AggregateRootHandler
{
    public function __construct(
        private DtoCacheHandlerInterface $cacheHandler,
    ) {
    }

    public function handle(callable $handler, \Stringable $query): \stdClass
    {
        $reflection = new \ReflectionFunction(\Closure::fromCallable($handler));
        $returnType = $reflection->getReturnType();
        if (! ($returnType instanceof \ReflectionNamedType)) {
            throw new \RuntimeException('Handler must return a named type');
        }
        $typeName = $returnType->getName();

        $model = $this->cacheHandler->get((string) $query, $typeName);

        if ($model === null) {
            $model = $handler($query);
            if ($model instanceof AbstractAggregateRoot) {
                $model->_clearEventLogItems();
                $this->cacheHandler->set($model);
            } else {
                throw new \RuntimeException(sprintf('Handler must return an instance of %s', AbstractAggregateRoot::class));
            }
        }

        return $model->jsonSerialize();
    }
}
