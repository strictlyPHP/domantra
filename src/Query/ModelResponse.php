<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query;

class ModelResponse implements ResponseInterface
{
    public function __construct(
        public readonly \stdClass $item,
    ) {
    }

    public function jsonSerialize(): \stdClass
    {
        return (object) [
            'item' => $this->item,
        ];
    }
}
