<?php

declare(strict_types=1);

namespace RequestMapper\ArgumentResolver;

use RequestMapper\Attribute\FromHeader;
use RequestMapper\Attribute\FromPath;
use RequestMapper\Attribute\FromUploads;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class RequestMapperValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {}

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $isQueryString = !empty($argument->getAttributesOfType(MapQueryString::class));
        $isRequestPayload = !empty($argument->getAttributesOfType(MapRequestPayload::class));

        if (!$isQueryString && !$isRequestPayload) {
            return [];
        }

        $type = $argument->getType();
        if (!$type || !class_exists($type) || !$this->hasRequestMapperAttributes($type)) {
            return [];
        }

        $data = $isQueryString ? $request->query->all() : ($request->request->all() ?: []);

        $object = $this->serializer->denormalize($data, $type, 'csv', ['filter_bool' => true]);

        $errors = $this->validator->validate($object);
        if (count($errors) > 0) {
            throw new UnprocessableEntityHttpException((string) $errors);
        }

        yield $object;
    }

    private function hasRequestMapperAttributes(string $class): bool
    {
        $constructor = (new \ReflectionClass($class))->getConstructor();
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
}
