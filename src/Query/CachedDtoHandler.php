<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query;

use ReflectionNamedType;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerInterface;
use StrictlyPHP\Domantra\Query\Exception\ItemNotFoundException;
use StrictlyPHP\Domantra\Query\Handlers\DtoHandlerHandlerInterface;

class CachedDtoHandler
{
    public function __construct(
        private DtoCacheHandlerInterface $cacheHandler,
    ) {
    }

    /**
     * @throws ItemNotFoundException
     */
    public function handle(\Stringable $query, DtoHandlerHandlerInterface $handler): \stdClass
    {
        $reflection = new \ReflectionFunction(\Closure::fromCallable($handler));
        /** @var ReflectionNamedType $returnType */
        $returnType = $reflection->getReturnType();
        $typeName = $returnType->getName();

        $dto = $this->cacheHandler->get((string) $query, $typeName);

        if ($dto === null) {
            $dto = $handler->__invoke($query);
            $this->cacheHandler->set($dto);
        }

        return $dto->jsonSerialize();
    }
}
