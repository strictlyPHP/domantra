<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query;

use ReflectionNamedType;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerInterface;
use StrictlyPHP\Domantra\Domain\CachedDtoInterface;
use StrictlyPHP\Domantra\Query\Exception\ItemNotFoundException;
use StrictlyPHP\Domantra\Query\Handlers\SingleHandlerInterface;
use StrictlyPHP\Domantra\Query\Transformer\DtoTransformer;

class AggregateRootHandler
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
    public function handle(\Stringable $query, SingleHandlerInterface $handler, ?string $role): \stdClass
    {
        $reflection = new \ReflectionFunction(\Closure::fromCallable($handler));

        /** @var ReflectionNamedType $modelReturnType */
        $modelReturnType = $reflection->getReturnType();
        $modelClass = $modelReturnType->getName();

        $modelReflection = new \ReflectionClass($modelClass);
        $getDtoMethod = $modelReflection->getMethod('getDto');

        /** @var ReflectionNamedType $dtoReturnType */
        $dtoReturnType = $getDtoMethod->getReturnType();
        $dtoClass = $dtoReturnType->getName();
        if ($dtoClass === CachedDtoInterface::class) {
            throw new \RuntimeException(sprintf(
                '%s is not allowed as a return type for %s. Use the class of the Dto instead.',
                CachedDtoInterface::class,
                $modelClass
            ));
        }
        $dto = $this->cacheHandler->get((string) $query, $dtoClass);

        if ($dto !== null) {
            return $this->dtoTransformer->transform($dto, $role);
        }

        $model = $handler->__invoke($query);
        $model->_clearEventLogItems();
        $dto = $model->getDto();
        $this->cacheHandler->set($dto);

        return $this->dtoTransformer->transform($dto, $role);
    }
}
