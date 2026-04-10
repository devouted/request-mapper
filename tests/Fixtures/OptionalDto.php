<?php

declare(strict_types=1);

namespace RequestMapper\Tests\Fixtures;

use RequestMapper\Attribute\FromHeader;
use RequestMapper\Attribute\FromPath;

class OptionalDto
{
    public function __construct(
        #[FromPath]
        public int $id,

        #[FromHeader(name: 'X-Token', required: false)]
        public string $token = 'default',
    ) {
    }
}
