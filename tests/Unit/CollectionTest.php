<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use PhpCli\Collection;

final class CollectionTest extends TestCase
{
    public function testClear()
    {
        $C = new Collection(['one', 'two', 'three']);

        $this->assertEquals(3, $C->count());

        $C->clear();

        $this->assertEquals(0, $C->count());
    }

    public function testColumn()
    {
        $C = new Collection([
            [
                'id' => 2135,
                'first_name' => 'John',
                'last_name' => 'Doe'
            ], [
                'id' => 3245,
                'first_name' => 'Sally',
                'last_name' => 'Smith'
            ], [
                'id' => 5342,
                'first_name' => 'Jane',
                'last_name' => 'Jones'
            ], [
                'id' => 5623,
                'first_name' => 'Peter',
                'last_name' => 'Doe'
            ]
        ]);

        $expected = new Collection([
            'John',
            'Sally',
            'Jane',
            'Peter'
        ]);
        $actual = $C->column('first_name');

        $this->assertEquals($expected, $actual);

        $C = new Collection([
            (object) [
                'id' => 2135,
                'first_name' => 'John',
                'last_name' => 'Doe'
            ], (object) [
                'id' => 3245,
                'first_name' => 'Sally',
                'last_name' => 'Smith'
            ], (object) [
                'id' => 5342,
                'first_name' => 'Jane',
                'last_name' => 'Jones'
            ], (object) [
                'id' => 5623,
                'first_name' => 'Peter',
                'last_name' => 'Doe'
            ]
        ]);

        $actual = $C->column('first_name');

        $this->assertInstanceOf(\stdClass::class, $C->first());
        $this->assertEquals($expected, $actual);
    }

    public function testContains()
    {
        $A = (object) [
            'id' => 2135,
            'first_name' => 'John',
            'last_name' => 'Doe'
        ];
        $B = (object) [
            'id' => 2135,
            'first_name' => 'John',
            'last_name' => 'Doe'
        ];
        $C = (object) [
            'id' => 5342,
            'first_name' => 'Jane',
            'last_name' => 'Jones'
        ];
        $D = (object) [
            'id' => 5623,
            'first_name' => 'Peter',
            'last_name' => 'Doe'
        ];

        $Collection = new Collection([$A, $C]);

        $this->assertTrue($Collection->contains($A));
        $this->assertTrue($Collection->contains($B));
        $this->assertTrue($Collection->contains($C));
        $this->assertFalse($Collection->contains($D));

        $this->assertTrue($Collection->contains(function ($item) use ($A) {
            return $item->id === $A->id;
        }));
        $this->assertFalse($Collection->contains(function ($item) use ($D) {
            return $item->id === $D->id;
        }));
    }

    public function testCopy()
    {
        $C = new Collection(['one', 'two', 'three']);
        $D = $C->copy();

        $this->assertEquals(3, $C->count());

        $C->clear();
        $expected = ['one', 'two', 'three'];
        $actual = $D->toArray();

        $this->assertEquals(0, $C->count());
        $this->assertEquals(3, $D->count());
        $this->assertEquals($expected, $actual);
    }

    public function testCount()
    {
        $C = new Collection(['one', 'two', 'three']);

        $this->assertEquals(3, $C->count());
        $this->assertEquals(3, count($C));
    }

    public function testEmpty()
    {
        $C = new Collection();

        $this->assertTrue($C->empty());

        $C = new Collection(['one', 'two']);

        $this->assertFalse($C->empty());
    }

    public function testFilter()
    {
        $C = new Collection([1, 2, 3, 4]);
        $Evens = $C->filter(function ($item) {
            return $item % 2 === 0;
        });
        $expected = [2, 4];
        $actual = $Evens->toArray();

        $this->assertEquals(4, $C->count());
        $this->assertEquals(2, $Evens->count());
        $this->assertEquals($expected, $actual);
    }

    public function testFirst()
    {
        $C = new Collection(['one', 'two']);
        $expected = 'one';
        $actual = $C->first();

        $this->assertEquals($expected, $actual);

        $C = new Collection([1, 2, 3, 4, 5, 6, 7]);
        $expected = 3;
        $actual = $C->first(function ($item) {
            return $item % 3 === 0;
        });

        $this->assertEquals($expected, $actual);
    }

    public function testLast()
    {
        $C = new Collection(['one', 'two']);
        $expected = 'two';
        $actual = $C->last();

        $this->assertEquals($expected, $actual);

        $C = new Collection([1, 2, 3, 4, 5, 6, 7]);
        $expected = 6;
        $actual = $C->last(function ($item) {
            return $item % 3 === 0;
        });

        $this->assertEquals($expected, $actual);
    }

    public function testJsonSerialize()
    {
        $C = new Collection(['one', 'two']);
        $expected = '["one","two"]';

        $actual = json_encode($C);

        $this->assertEquals($expected, $actual);
    }

    // public function testMap()
    // {
    //     $C = new Collection([1, 2, 3]);
    //     $expected = [2, 4, 6];
    //     $C->map(function ($item) {
    //         return $item * 2;
    //     });
    //     $actual = $C->toArray();

    //     $this->assertEquals($expected, $actual);
    // }

    public function testPop()
    {
        $C = new Collection([1, 2, 3, 4]);
        $expected = 4;
        $actual = $C->pop();

        $this->assertEquals($expected, $actual);
        $this->assertEquals(3, $C->count());
    }

    public function testPull()
    {
        $C = new Collection([1, 2, 3, 4]);
        $Pulled = $C->pull(function ($item) {
            return $item % 2 === 0;
        });
        $expected = [2, 4];
        $actual = $Pulled->toArray();

        $this->assertEquals(2, $C->count());
        $this->assertEquals(2, $Pulled->count());
        $this->assertEquals($expected, $actual);

        $expected = [0 => 1, 2 => 3];
        $actual = $C->toArray();

        $this->assertEquals($expected, $actual);
    }

    public function testPush()
    {
        $C = new Collection([1, 2]);

        $this->assertEquals(2, $C->count());

        $count = $C->push(3);

        $this->assertEquals(3, $C->count());
        $this->assertEquals(3, $count);
    }

    public function testPushInvalidType()
    {
        $C = new Collection([1, 2]);

        $this->assertEquals(2, $C->count());

        $this->expectException(\PhpCli\Exceptions\InvalidTypeException::class);

        $C->push('3');
    }

    public function testSet()
    {
        $C = new Collection([1, 2]);

        $this->assertEquals(2, $C->count());

        $C->set(2, 3);

        $this->assertEquals(3, $C->count());
        $this->assertEquals(3, $C->get(2));

        $C = new Collection(['one' => 1, 'two' => 2]);

        $this->assertEquals(2, $C->count());

        $C->set('three', 3);

        $this->assertEquals(3, $C->count());
        $this->assertEquals(3, $C->get('three'));
    }

    public function testShift()
    {
        $C = new Collection([1, 2, 3, 4]);
        $expected = 1;
        $actual = $C->shift();

        $this->assertEquals($expected, $actual);
        $this->assertEquals(3, $C->count());
    }

    public function testToArray()
    {
        $C = new Collection([2, 4]);
        $expected = [2, 4];
        $actual = $C->toArray();

        $this->assertEquals($expected, $actual);
    }

    public function testTransform()
    {
        $C = new Collection([1, 2, 3]);
        $expected = [2, 4, 6];
        $C->transform(function ($item) {
            return $item * 2;
        });
        $actual = $C->toArray();

        $this->assertEquals($expected, $actual);
    }

    public function testType()
    {
        $C = new Collection([2]);
        $expected = 'integer';
        $actual = $C->type();

        $this->assertEquals($expected, $actual);

        $C = new Collection([new \stdClass()]);
        $expected = \stdClass::class;
        $actual = $C->type();

        $this->assertEquals($expected, $actual);
    }

    public function testUnshift()
    {
        $C = new Collection([2, 3, 4]);
        $expected = [1, 2, 3, 4];
        $count = $C->unshift(1);
        $actual = $C->toArray();

        $this->assertEquals($expected, $actual);
        $this->assertEquals(4, $C->count());
        $this->assertEquals(4, $count);
    }
}