<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Cache;

abstract class AbstractDtoCacheHandler implements DtoCacheHandlerInterface
{
    /**
     * @param class-string $class
     */
    protected function getKey(
        string $cacheKey,
        string $class,
        ?string $version = null
    ): string {
        if (! $version) {
            $version = $this->fingerprintClassProperties($class);
        }

        return sprintf(
            '%s:%s:%s:%s',
            'resource-key',
            $class,
            $cacheKey,
            $version
        );
    }

    /**
     * @param class-string $class
     */
    protected function fingerprintClassProperties(string $class): string
    {
        $ref = new \ReflectionClass($class);
        $properties = [];

        foreach ($ref->getProperties() as $prop) {
            $type = $prop->getType();
            $typeName = '';

            if ($type instanceof \ReflectionNamedType) {
                $typeName = ($type->allowsNull() ? '?' : '') . $type->getName();
            } elseif ($type instanceof \ReflectionUnionType) {
                $typeName = ($type->allowsNull() ? '?' : '') .
                    implode('|', array_map(fn ($t) => $t->getName(), $type->getTypes()));
            }

            $properties[$prop->getName()] = $typeName;
        }

        ksort($properties); // Sort keys for consistent hashing
        $json = json_encode($properties, JSON_UNESCAPED_UNICODE);

        return hash('sha256', $json);
    }
}
