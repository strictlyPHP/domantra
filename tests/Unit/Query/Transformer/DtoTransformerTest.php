<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Query\Transformer;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Domantra\Query\Attributes\RequiresAuthenticatedUser;
use StrictlyPHP\Domantra\Query\Transformer\DtoTransformer;

class DtoTransformerTest extends TestCase
{
    protected DtoTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new DtoTransformer();
    }

    public function testTransformsWithNoAttributes(): void
    {
        $result = $this->transformer->transform(
            new class() {
                public string $foo = 'foo';

                public string $bar = 'bar';
            },
            null
        );

        $this->assertEquals((object) [
            'foo' => 'foo',
            'bar' => 'bar',
        ], $result);
    }

    public function testItHidesPropertiesWithAuthenticatedUserAttribute(): void
    {
        $result = $this->transformer->transform(
            new class() {
                #[RequiresAuthenticatedUser]
                public string $foo = 'foo';

                public string $bar = 'bar';
            },
            null
        );

        $this->assertEquals((object) [
            'bar' => 'bar',
        ], $result);
    }

    /**
     * @dataProvider userProvider
     */
    public function testItShowsPropertiesWithAuthenticatedUserAttribute(string $role): void
    {
        $result = $this->transformer->transform(
            new class() {
                #[RequiresAuthenticatedUser]
                public string $foo = 'foo';

                public string $bar = 'bar';
            },
            $role
        );

        $this->assertEquals((object) [
            'foo' => 'foo',
            'bar' => 'bar',
        ], $result);
    }

    public function testItHidesPropertiesWithAuthenticatedUserRolesAttribute(): void
    {
        $result = $this->transformer->transform(
            new class() {
                #[RequiresAuthenticatedUser(['ADMIN'])]
                public string $foo = 'foo';

                public string $bar = 'bar';
            },
            null
        );

        $this->assertEquals((object) [
            'bar' => 'bar',
        ], $result);
    }

    /**
     * @dataProvider userProvider
     */
    public function testItShowsPropertiesWithAuthenticatedUserRolesAttribute(string $role): void
    {
        $result = $this->transformer->transform(
            new class() {
                #[RequiresAuthenticatedUser(['ADMIN'])]
                public string $foo = 'foo';

                public string $bar = 'bar';
            },
            $role
        );

        $expected = [
            'USER' => (object) [
                'bar' => 'bar',
            ],
            'ADMIN' => (object) [
                'foo' => 'foo',
                'bar' => 'bar',
            ],
        ];
        $this->assertEquals($expected[$role], $result);
    }

    /**
     * @return array<int, array<string>>
     */
    public function userProvider(): array
    {
        return [
            ['USER'],
            ['ADMIN'],
        ];
    }
}
