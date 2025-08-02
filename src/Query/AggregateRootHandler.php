<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query;

use ReflectionNamedType;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerInterface;
use StrictlyPHP\Domantra\Query\Exception\ModelNotFoundException;
use StrictlyPHP\Domantra\Query\Handlers\SingleHandlerInterface;

class AggregateRootHandler
{
    public function __construct(
        private DtoCacheHandlerInterface $cacheHandler,
    ) {
    }

    /**
     * @throws ModelNotFoundException
     */
    public function handle(\Stringable $query, SingleHandlerInterface $handler): \stdClass
    {
        $reflection = new \ReflectionFunction(\Closure::fromCallable($handler));
        /** @var ReflectionNamedType $returnType */
        $returnType = $reflection->getReturnType();
        $typeName = $returnType->getName();

        $model = $this->cacheHandler->get((string) $query, $typeName);

        if ($model === null) {
            $model = $handler->__invoke($query);
            $model->_clearEventLogItems();
            $this->cacheHandler->set($model);
        }

        return $model->jsonSerialize();
    }
}
