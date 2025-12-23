<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query;

use ReflectionNamedType;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerInterface;
use StrictlyPHP\Domantra\Query\Exception\ItemNotFoundException;
use StrictlyPHP\Domantra\Query\Handlers\DtoHandlerHandlerInterface;
use StrictlyPHP\Domantra\Query\Transformer\DtoTransformer;

class CachedDtoHandler
{
    private DtoTransformer $dtoTransformer;

    public function __construct(
        private DtoCacheHandlerInterface $cacheHandler,
        ?DtoTransformer $dtoTransformer = null
    ) {
        if ($dtoTransformer === null) {
            $dtoTransformer = new DtoTransformer();
        }
        $this->dtoTransformer = $dtoTransformer;
    }

    /**
     * @throws ItemNotFoundException
     */
    public function handle(\Stringable $query, DtoHandlerHandlerInterface $handler, ?string $role): \stdClass
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

        return $this->dtoTransformer->transform($dto, $role);
    }
}
