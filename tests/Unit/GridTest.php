<?php declare(strict_types=1);

namespace Tests\Unit;

use PhpCli\Grid;
use Tests\TestCase;

class GridTest extends TestCase
{
    public function testColumnNotIndexedException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Grid must be initialized with an indexed array.');

        $g = new Grid([
            ['o', 'n', 'e'],
            'row2' => ['t', 'w', 'o'],
            ['t', 'h', 'r', 'e', 'e']
        ]);
    }

    public function testColumnNotNestedException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Grid must be initialized with an indexed array of arrays.');

        $g = new Grid(
            ['o', 'n', 'e']
        );
    }

    public function testColumnNestedArrayNotIndexedException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Grid must be initialized with an indexed array of indexed arrays.');

        new Grid([
            ['o', 'n', 'e'],
            ['col1' => 't', 'w', 'o'],
            ['t', 'h', 'r', 'e', 'e']
        ]);
    }

    public function testColumn()
    {
        $g = new Grid([
            ['o', 'n', 'e'],
            ['t', 'w', 'o'],
            ['t', 'h', 'r', 'e', 'e']
        ]);

        $expected = ['n', 'w', 'h'];
        $actual = $g->column(1)->toArray();

        $this->assertEquals($expected, $actual);

        $expected = [null, null, 'e'];
        $actual = $g->column(3)->toArray();

        $this->assertEquals($expected, $actual);
    }

    public function testGetInvalidRowOffsetException()
    {
        $g = new Grid([
            ['o', 'n', 'e'],
            ['t', 'w', 'o'],
            ['t', 'h', 'r', 'e', 'e']
        ]);

        $this->expectException(\RangeException::class);
        $this->expectExceptionMessage('3: invalid row offest');

        $g->get(3, 1);
    }

    public function testGetInvalidColumnOffsetException()
    {
        $g = new Grid([
            ['o', 'n', 'e'],
            ['t', 'w', 'o'],
            ['t', 'h', 'r', 'e', 'e']
        ]);

        $this->expectException(\RangeException::class);
        $this->expectExceptionMessage('5: invalid column offest');

        $g->get(1, 5);
    }

    public function testGet()
    {
        $g = new Grid([
            ['o', 'n', 'e'],
            ['t', 'w', 'o'],
            ['t', 'h', 'r', 'e', 'e']
        ]);

        $expected = 'o';
        $actual = $g->get(1, 2);

        $this->assertEquals($expected, $actual);

        $expected = null;
        $actual = $g->get(1, 4);

        $this->assertEquals($expected, $actual);
    }

    public function testGetData()
    {
        $g = new Grid([
            ['o', 'n', 'e'],
            ['t', 'w', 'o'],
            ['t', 'h', 'r', 'e', 'e'
            ]
        ]);
        $expected = [
            ['o', 'n', 'e', null, null],
            ['t', 'w', 'o', null, null],
            ['t', 'h', 'r', 'e', 'e']
        ];
        $actual = $g->getData()->map(function ($Vector) {
            return $Vector->toArray();
        })->toArray();

        $this->assertEquals($expected, $actual);
    }

    public function testHeight()
    {
        $g = new Grid([
            ['o', 'n', 'e'],
            ['t', 'w', 'o'],
            ['t', 'h', 'r', 'e', 'e']
        ]);
        $expected = 3;
        $actual = $g->height();

        $this->assertEquals($expected, $actual);
    }

    public function testRow()
    {
        $g = new Grid([
            ['o', 'n', 'e'],
            ['t', 'w', 'o'],
            ['t', 'h', 'r', 'e', 'e']
        ]);

        $expected = ['t', 'w', 'o', null, null];
        $actual = $g->row(1)->toArray();

        $this->assertEquals($expected, $actual);

        $expected = ['t', 'h', 'r', 'e', 'e'];
        $actual = $g->row(2)->toArray();

        $this->assertEquals($expected, $actual);
    }

    public function testSet()
    {
        $g = new Grid([
            ['o', 'n', 'e'],
            ['t', 'w', 'o'],
            ['t', 'h', 'r', 'e', 'e']
        ]);

        $expected = 'WOW';

        $g->set(2, 2, $expected);
        $data = $g->getData()->toArray();
        $actual = $data[2][2];

        $this->assertEquals($expected, $actual);
    }

    public function testSetData()
    {
        $expected = [
            [6, 7, 8],
            [9, 3, 2]
        ];
        $g = new Grid([
            [1, 2, 3],
            [4, 5, 6]
        ]);

        $g->setData($expected);
        $actual = $g->getData()->map(function ($Vector) {
            return $Vector->toArray();
        })->toArray();

        $this->assertEquals($expected, $actual);
    }

    public function toArray()
    {
        $expected = [
            [6, 7, 8],
            [9, 3, 2]
        ];
        $g = new Grid($expected);
        $actual = $g->toArray();

        $this->assertEquals($expected, $actual);
    }

    public function testWidth()
    {
        $g = new Grid([
            ['o', 'n', 'e'],
            ['t', 'w', 'o'],
            ['t', 'h', 'r', 'e', 'e']
        ]);
        $expected = 5;
        $actual = $g->width();

        $this->assertEquals($expected, $actual);
    }
}