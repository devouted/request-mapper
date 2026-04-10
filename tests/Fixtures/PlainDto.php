<?php

declare(strict_types=1);

namespace RequestMapper\Tests\Fixtures;

class PlainDto
{
    public function __construct(
        public string $name,
    ) {
    }
}
