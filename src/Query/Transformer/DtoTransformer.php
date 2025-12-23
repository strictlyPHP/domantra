<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query\Transformer;

use ReflectionProperty;
use StrictlyPHP\Domantra\Query\Attributes\RequiresAuthenticatedUser;

class DtoTransformer
{
    public function transform(object $object, ?string $role): \stdClass
    {
        $ref = new \ReflectionClass($object);
        $props = $ref->getProperties(ReflectionProperty::IS_PUBLIC);

        $output = [];

        foreach ($props as $prop) {
            $value = $prop->getValue($object);
            $name = $prop->getName();

            $attributes = $prop->getAttributes(RequiresAuthenticatedUser::class);
            if (! empty($attributes)) {
                $roles = $attributes[0]->newInstance()->roles;
                if (empty($roles) && $role === null) {
                    continue;
                }
                if (
                    ! empty($roles) &&
                    ! in_array($role, $roles, true)
                ) {
                    continue;
                }
            }
            $output[$name] = $value;
        }

        return (object) $output;
    }
}
