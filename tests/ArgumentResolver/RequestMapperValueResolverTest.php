<?php

declare(strict_types=1);

namespace RequestMapper\Tests\ArgumentResolver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RequestMapper\ArgumentResolver\RequestMapperValueResolver;
use RequestMapper\Tests\Fixtures\FullDto;
use RequestMapper\Tests\Fixtures\PlainDto;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

interface TestSerializerInterface extends SerializerInterface, DenormalizerInterface {}

class RequestMapperValueResolverTest extends TestCase
{
    private function createResolver(
        TestSerializerInterface $serializer = null,
        ValidatorInterface $validator = null,
    ): RequestMapperValueResolver {
        return new RequestMapperValueResolver(
            $serializer ?? $this->createStub(TestSerializerInterface::class),
            $validator ?? $this->createStub(ValidatorInterface::class),
        );
    }

    private function createArgument(string $type, array $attributes = []): ArgumentMetadata
    {
        return new ArgumentMetadata('dto', $type, false, false, null, false, $attributes);
    }

    #[Test]
    public function it_returns_empty_when_no_map_attribute(): void
    {
        $result = iterator_to_array(
            $this->createResolver()->resolve(Request::create('/'), $this->createArgument(FullDto::class))
        );

        $this->assertSame([], $result);
    }

    #[Test]
    public function it_returns_empty_when_type_has_no_request_mapper_attributes(): void
    {
        $result = iterator_to_array(
            $this->createResolver()->resolve(Request::create('/'), $this->createArgument(PlainDto::class, [new MapQueryString()]))
        );

        $this->assertSame([], $result);
    }

    #[Test]
    public function it_returns_empty_when_type_is_not_a_class(): void
    {
        $result = iterator_to_array(
            $this->createResolver()->resolve(Request::create('/'), $this->createArgument('string', [new MapQueryString()]))
        );

        $this->assertSame([], $result);
    }

    #[Test]
    public function it_resolves_with_map_query_string_using_query_data(): void
    {
        $dto = new FullDto(id: 1, language: 'en');

        $serializer = $this->createMock(TestSerializerInterface::class);
        $serializer->expects($this->once())
            ->method('denormalize')
            ->with(['foo' => 'bar'], FullDto::class, 'csv', ['filter_bool' => true])
            ->willReturn($dto);

        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $result = iterator_to_array(
            $this->createResolver($serializer, $validator)->resolve(
                Request::create('/?foo=bar'),
                $this->createArgument(FullDto::class, [new MapQueryString()])
            )
        );

        $this->assertCount(1, $result);
        $this->assertSame($dto, $result[0]);
    }

    #[Test]
    public function it_resolves_with_map_query_string_and_empty_query(): void
    {
        $dto = new FullDto(id: 0, language: 'default');

        $serializer = $this->createMock(TestSerializerInterface::class);
        $serializer->expects($this->once())
            ->method('denormalize')
            ->with([], FullDto::class, 'csv', ['filter_bool' => true])
            ->willReturn($dto);

        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $result = iterator_to_array(
            $this->createResolver($serializer, $validator)->resolve(
                Request::create('/'),
                $this->createArgument(FullDto::class, [new MapQueryString()])
            )
        );

        $this->assertCount(1, $result);
        $this->assertSame($dto, $result[0]);
    }

    #[Test]
    public function it_resolves_with_map_request_payload_using_request_data(): void
    {
        $dto = new FullDto(id: 1, language: 'en');

        $serializer = $this->createMock(TestSerializerInterface::class);
        $serializer->expects($this->once())
            ->method('denormalize')
            ->with(['name' => 'test'], FullDto::class, 'csv', ['filter_bool' => true])
            ->willReturn($dto);

        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $result = iterator_to_array(
            $this->createResolver($serializer, $validator)->resolve(
                Request::create('/', 'POST', ['name' => 'test']),
                $this->createArgument(FullDto::class, [new MapRequestPayload()])
            )
        );

        $this->assertCount(1, $result);
        $this->assertSame($dto, $result[0]);
    }

    #[Test]
    public function it_resolves_with_map_request_payload_and_empty_body(): void
    {
        $dto = new FullDto(id: 0, language: 'default');

        $serializer = $this->createMock(TestSerializerInterface::class);
        $serializer->expects($this->once())
            ->method('denormalize')
            ->with([], FullDto::class, 'csv', ['filter_bool' => true])
            ->willReturn($dto);

        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $result = iterator_to_array(
            $this->createResolver($serializer, $validator)->resolve(
                Request::create('/', 'POST'),
                $this->createArgument(FullDto::class, [new MapRequestPayload()])
            )
        );

        $this->assertCount(1, $result);
        $this->assertSame($dto, $result[0]);
    }

    #[Test]
    public function it_throws_on_validation_errors(): void
    {
        $dto = new FullDto(id: 1, language: 'en');

        $serializer = $this->createStub(TestSerializerInterface::class);
        $serializer->method('denormalize')->willReturn($dto);

        $validator = $this->createStub(ValidatorInterface::class);
        $violation = new ConstraintViolation('Invalid', null, [], $dto, 'id', 1);
        $validator->method('validate')->willReturn(new ConstraintViolationList([$violation]));

        $this->expectException(UnprocessableEntityHttpException::class);

        iterator_to_array(
            $this->createResolver($serializer, $validator)->resolve(
                Request::create('/?foo=bar'),
                $this->createArgument(FullDto::class, [new MapQueryString()])
            )
        );
    }
}
