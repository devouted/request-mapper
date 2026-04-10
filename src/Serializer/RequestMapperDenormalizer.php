<?php

declare(strict_types=1);

namespace RequestMapper\Serializer;

use RequestMapper\Attribute\FromHeader;
use RequestMapper\Attribute\FromPath;
use RequestMapper\Attribute\FromUploads;
use RequestMapper\Exception\MissingRequestValueException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class RequestMapperDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    private const CONTEXT_KEY = 'request_mapper_processed';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !is_array($data)) {
            return $this->denormalizer->denormalize($data, $type, $format, $context + [self::CONTEXT_KEY => true]);
        }

        $context['request'] = $request;
        $constructor = (new \ReflectionClass($type))->getConstructor();

        if (!$constructor) {
            return $this->denormalizer->denormalize($data, $type, $format, $context + [self::CONTEXT_KEY => true]);
        }

        foreach ($constructor->getParameters() as $param) {
            $data = $this->resolveFromHeader($param, $request, $data);
            $data = $this->resolveFromPath($param, $request, $data);
            $data = $this->resolveFromUploads($param, $request, $data);
        }

        return $this->denormalizer->denormalize($data, $type, $format, $context + [self::CONTEXT_KEY => true]);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if (isset($context[self::CONTEXT_KEY]) || !class_exists($type)) {
            return false;
        }

        $constructor = (new \ReflectionClass($type))->getConstructor();

        if (!$constructor) {
            return false;
        }

        foreach ($constructor->getParameters() as $param) {
            if (
                !empty($param->getAttributes(FromHeader::class)) ||
                !empty($param->getAttributes(FromPath::class)) ||
                !empty($param->getAttributes(FromUploads::class))
            ) {
                return true;
            }
        }

        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => false];
    }

    private function resolveFromHeader(\ReflectionParameter $param, $request, array $data): array
    {
        $attrs = $param->getAttributes(FromHeader::class);

        if (empty($attrs)) {
            return $data;
        }

        $attr = $attrs[0]->newInstance();
        $name = $attr->name ?? $param->getName();
        $value = $request->headers->get($name);

        if ($value !== null) {
            $data[$param->getName()] = $value;
        } elseif ($attr->required) {
            throw new MissingRequestValueException(sprintf('Missing required header "%s".', $name));
        }

        return $data;
    }

    private function resolveFromPath(\ReflectionParameter $param, $request, array $data): array
    {
        $attrs = $param->getAttributes(FromPath::class);

        if (empty($attrs)) {
            return $data;
        }

        $attr = $attrs[0]->newInstance();
        $name = $attr->name ?? $param->getName();
        $value = $request->attributes->get($name);

        if ($value !== null) {
            $data[$param->getName()] = $this->castToType($value, $param->getType());
        } elseif ($attr->required) {
            throw new MissingRequestValueException(sprintf('Missing required path parameter "%s".', $name));
        }

        return $data;
    }

    private function resolveFromUploads(\ReflectionParameter $param, $request, array $data): array
    {
        $attrs = $param->getAttributes(FromUploads::class);

        if (empty($attrs)) {
            return $data;
        }

        $attr = $attrs[0]->newInstance();
        $name = $attr->name ?? $param->getName();
        $files = $request->files->all($name);

        if (!empty($files)) {
            $data[$param->getName()] = $files;
        } elseif ($attr->required) {
            throw new MissingRequestValueException(sprintf('Missing required uploaded files for "%s".', $name));
        }

        return $data;
    }

    private function castToType(mixed $value, ?\ReflectionType $type): mixed
    {
        if (!$type || !($type instanceof \ReflectionNamedType)) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => (string) $value,
            default => $value,
        };
    }
}
