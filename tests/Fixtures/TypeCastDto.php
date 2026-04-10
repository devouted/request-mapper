<?php

declare(strict_types=1);

namespace RequestMapper\Tests\Fixtures;

use RequestMapper\Attribute\FromPath;

class TypeCastDto
{
    public function __construct(
        #[FromPath]
        public int $intVal,

        #[FromPath]
        public float $floatVal,

        #[FromPath]
        public bool $boolVal,

        #[FromPath]
        public string $stringVal,
    ) {
    }
}
