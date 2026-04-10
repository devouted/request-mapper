<?php

declare(strict_types=1);

namespace RequestMapper\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class FromPath
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly bool $required = true,
    ) {
    }
}
