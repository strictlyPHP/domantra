<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query\Response;

class ModelResponse implements ResponseInterface
{
    public function __construct(
        public readonly \stdClass $item,
        public readonly int $code = 200,
    ) {
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function jsonSerialize(): \stdClass
    {
        return (object) [
            'item' => $this->item,
        ];
    }
}
