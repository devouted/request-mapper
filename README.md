# Request Mapper

Symfony attributes for mapping HTTP request headers, path parameters and uploaded files to object constructor parameters via the Serializer denormalization pipeline.

## Installation

```bash
composer require devouted/request-mapper
```

## Usage

Mark constructor parameters with attributes to indicate their source:

```php
use RequestMapper\Attribute\FromHeader;
use RequestMapper\Attribute\FromPath;
use RequestMapper\Attribute\FromUploads;

class GetArticleQuery
{
    public function __construct(
        #[FromPath]
        public int $articleId,

        #[FromHeader(name: 'Accept-Language')]
        public string $language,
    ) {
    }
}

class UploadFileCommand
{
    public function __construct(
        #[FromPath]
        public int $visitId,

        #[FromUploads]
        public array $files = [],
    ) {
    }
}
```

### Attributes

| Attribute       | Source                          | Example                              |
|-----------------|---------------------------------|--------------------------------------|
| `#[FromHeader]` | HTTP request header             | `#[FromHeader(name: 'X-Token')]`     |
| `#[FromPath]`   | Route parameter                 | `#[FromPath(name: 'id')]`            |
| `#[FromUploads]`| Uploaded files (`$_FILES`)      | `#[FromUploads]`                     |

All attributes accept optional `name` (defaults to parameter name) and `required` (defaults to `true`).

`#[FromPath]` automatically casts values to the parameter's PHP type (`int`, `float`, `bool`, `string`).

## Configuration

If Symfony autoconfiguration is enabled, the denormalizer is registered automatically. Otherwise register it manually:

```yaml
# config/services.yaml
services:
    RequestMapper\Serializer\RequestMapperDenormalizer:
        tags: ['serializer.normalizer']
```
