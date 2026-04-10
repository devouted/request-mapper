# Request Mapper

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg)](https://php.net/)
[![Tested on PHP 8.5](https://img.shields.io/badge/tested%20on-PHP%208.2%20|%208.3%20|%208.4%20|%208.5-brightgreen.svg)](https://php.net/)
[![Symfony](https://img.shields.io/badge/symfony-6.4%20|%207.x%20|%208.x-black.svg)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Symfony attributes for mapping HTTP request headers, path parameters and uploaded files to object constructor parameters via the Serializer denormalization pipeline.

## Requirements

- PHP >= 8.2 (tested on 8.2, 8.3, 8.4, 8.5)
- Symfony 6.4, 7.x or 8.x

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

## Value Resolver

The bundle includes `RequestMapperValueResolver` which integrates with Symfony's `#[MapQueryString]` and `#[MapRequestPayload]` attributes. This solves the problem where Symfony's default resolver skips mapping entirely when the query string or request body is empty — preventing `FromHeader`, `FromPath` and `FromUploads` attributes from being processed.

```php
use RequestMapper\Attribute\FromHeader;
use RequestMapper\Attribute\FromPath;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;

class ArticleController
{
    public function show(#[MapQueryString] GetArticleQuery $query): Response
    {
        // Works even when the query string is empty —
        // FromPath and FromHeader attributes are still resolved.
    }
}
```

The resolver requires `symfony/http-kernel` and `symfony/validator`.

## Configuration

If Symfony autoconfiguration is enabled, the denormalizer and value resolver are registered automatically. Otherwise register them manually:

```yaml
# config/services.yaml
services:
    RequestMapper\Serializer\RequestMapperDenormalizer:
        tags: ['serializer.normalizer']

    RequestMapper\ArgumentResolver\RequestMapperValueResolver:
        tags: ['controller.argument_value_resolver']
```
