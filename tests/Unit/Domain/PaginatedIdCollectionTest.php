<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Domain;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Domantra\Domain\PaginatedIdCollection;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserId;

class PaginatedIdCollectionTest extends TestCase
{
    public function testPaginatedIdCollectionCreation(): void
    {
        /** @var PaginatedIdCollection<UserId> $collection */
        $collection = new PaginatedIdCollection(
            ids: [
                new UserId('1'),
                new UserId('2'),
                new UserId('3'),
            ],
            page: 1,
            perPage: 10,
            totalItems: 3
        );

        $this->assertCount(3, $collection);
        $this->assertEquals(1, $collection->getPage());
        $this->assertEquals(10, $collection->getPerPage());
        $this->assertEquals(3, $collection->getTotalItems());
        $this->assertEquals(new UserId('1'), $collection[0]);
        $this->assertEquals(new UserId('2'), $collection[1]);
        $this->assertEquals(new UserId('3'), $collection[2]);
        $this->assertTrue(isset($collection[0]));
        $this->assertFalse(isset($collection[3]));

        $collection[] = new UserId('4');
        $this->assertCount(4, $collection);

        $collection[4] = new UserId('5');
        $this->assertCount(5, $collection);

        unset($collection[4]);
        $this->assertCount(4, $collection);
        $this->assertFalse(isset($collection[4]));

        $expected = [
            'ids' => [
                0 => '1',
                1 => '2',
                2 => '3',
                3 => '4',
            ],
            'page' => 1,
            'perPage' => 10,
            'totalItems' => 3,
        ];
        $this->assertEquals($expected, json_decode(json_encode($collection), true));
    }
}
