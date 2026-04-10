<?php

declare(strict_types=1);

namespace RequestMapper\Tests\Fixtures;

use RequestMapper\Attribute\FromHeader;
use RequestMapper\Attribute\FromPath;
use RequestMapper\Attribute\FromUploads;

class FullDto
{
    public function __construct(
        #[FromPath]
        public int $id,

        #[FromHeader(name: 'X-Custom-Required')]
        public string $language,

        #[FromUploads(required: false)]
        public array $files = [],
    ) {
    }
}
