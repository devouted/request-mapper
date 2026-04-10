<?php

declare(strict_types=1);

namespace RequestMapper\Tests\Serializer;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RequestMapper\Exception\MissingRequestValueException;
use RequestMapper\Serializer\RequestMapperDenormalizer;
use RequestMapper\Tests\Fixtures\FullDto;
use RequestMapper\Tests\Fixtures\OptionalDto;
use RequestMapper\Tests\Fixtures\PlainDto;
use RequestMapper\Tests\Fixtures\TypeCastDto;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class RequestMapperDenormalizerTest extends TestCase
{
    private function createDenormalizer(Request $request): RequestMapperDenormalizer
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $denormalizer = new RequestMapperDenormalizer($requestStack);

        $serializer = new Serializer([
            $denormalizer,
            new ObjectNormalizer(),
        ]);

        return $denormalizer;
    }

    #[Test]
    public function it_supports_classes_with_request_mapper_attributes(): void
    {
        $request = Request::create('/');
        $denormalizer = $this->createDenormalizer($request);

        $this->assertTrue($denormalizer->supportsDenormalization([], FullDto::class));
        $this->assertTrue($denormalizer->supportsDenormalization([], OptionalDto::class));
    }

    #[Test]
    public function it_does_not_support_classes_without_attributes(): void
    {
        $request = Request::create('/');
        $denormalizer = $this->createDenormalizer($request);

        $this->assertFalse($denormalizer->supportsDenormalization([], PlainDto::class));
    }

    #[Test]
    public function it_does_not_support_already_processed_context(): void
    {
        $request = Request::create('/');
        $denormalizer = $this->createDenormalizer($request);

        $this->assertFalse($denormalizer->supportsDenormalization(
            [],
            FullDto::class,
            null,
            ['request_mapper_processed' => true],
        ));
    }

    #[Test]
    public function it_resolves_path_header_and_uploads(): void
    {
        $request = Request::create('/articles/42');
        $request->attributes->set('id', '42');
        $request->headers->set('X-Custom-Required', 'pl');

        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'content');
        $uploaded = new UploadedFile($tmpFile, 'doc.pdf', 'application/pdf', null, true);
        $request->files->set('files', [$uploaded]);

        $denormalizer = $this->createDenormalizer($request);

        $result = $denormalizer->denormalize([], FullDto::class);

        $this->assertInstanceOf(FullDto::class, $result);
        $this->assertSame(42, $result->id);
        $this->assertSame('pl', $result->language);
        $this->assertCount(1, $result->files);
        $this->assertInstanceOf(UploadedFile::class, $result->files[0]);

        @unlink($tmpFile);
    }

    #[Test]
    public function it_casts_path_values_to_correct_types(): void
    {
        $request = Request::create('/');
        $request->attributes->set('intVal', '123');
        $request->attributes->set('floatVal', '3.14');
        $request->attributes->set('boolVal', 'true');
        $request->attributes->set('stringVal', '42');

        $denormalizer = $this->createDenormalizer($request);

        $result = $denormalizer->denormalize([], TypeCastDto::class);

        $this->assertSame(123, $result->intVal);
        $this->assertSame(3.14, $result->floatVal);
        $this->assertTrue($result->boolVal);
        $this->assertSame('42', $result->stringVal);
    }

    #[Test]
    public function it_throws_when_required_header_is_missing(): void
    {
        $request = Request::create('/');
        $request->attributes->set('id', '1');

        $denormalizer = $this->createDenormalizer($request);

        $this->expectException(MissingRequestValueException::class);
        $this->expectExceptionMessage('Missing required header "X-Custom-Required"');

        $denormalizer->denormalize([], FullDto::class);
    }

    #[Test]
    public function it_throws_when_required_path_param_is_missing(): void
    {
        $request = Request::create('/');
        $request->headers->set('X-Custom-Required', 'en');

        $denormalizer = $this->createDenormalizer($request);

        $this->expectException(MissingRequestValueException::class);
        $this->expectExceptionMessage('Missing required path parameter "id"');

        $denormalizer->denormalize([], FullDto::class);
    }

    #[Test]
    public function it_skips_optional_header_when_missing(): void
    {
        $request = Request::create('/');
        $request->attributes->set('id', '5');

        $denormalizer = $this->createDenormalizer($request);

        $result = $denormalizer->denormalize([], OptionalDto::class);

        $this->assertSame(5, $result->id);
        $this->assertSame('default', $result->token);
    }

    #[Test]
    public function it_uses_optional_header_value_when_present(): void
    {
        $request = Request::create('/');
        $request->attributes->set('id', '5');
        $request->headers->set('X-Token', 'abc123');

        $denormalizer = $this->createDenormalizer($request);

        $result = $denormalizer->denormalize([], OptionalDto::class);

        $this->assertSame(5, $result->id);
        $this->assertSame('abc123', $result->token);
    }

    #[Test]
    public function it_delegates_when_no_request_is_available(): void
    {
        $requestStack = new RequestStack();
        $denormalizer = new RequestMapperDenormalizer($requestStack);

        $innerDenormalizer = $this->createStub(DenormalizerInterface::class);
        $innerDenormalizer->method('denormalize')
            ->willReturn(new PlainDto('test'));

        $denormalizer->setDenormalizer($innerDenormalizer);

        $result = $denormalizer->denormalize(['name' => 'test'], PlainDto::class);

        $this->assertInstanceOf(PlainDto::class, $result);
    }
}
