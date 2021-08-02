<?php declare(strict_types=1);

use PhpCli\Arr;
use PHPUnit\Framework\TestCase;

class ArrTest extends TestCase {

    public function testFromObject()
    {
        $expected = ['key_' => 'the value', 'State' => 'complete'];
        $object = new \stdClass;
        $object->key_ = $expected['key_'];
        $object->State = $expected['State'];

        $actual = Arr::fromObject($object);

        $this->assertEquals($expected, $actual);
    }

    public function testGet()
    {
        $array = [
            'cars' => [
                'Tom' => [
                    'make' => 'Toyota',
                    'year' => '2011',
                ],
                'Carla' => [
                    'make' => 'Chevrolet',
                    'year' => '2014'
                ]
            ]
        ];

        $expected = 'Chevrolet';
        $actual = Arr::get($array, 'cars.Carla.make', '.');

        $this->assertEquals($expected, $actual);
    }

    public function testIsAssociative()
    {
        $this->assertFalse(Arr::isAssociative([
            'value', 'value1', 'value'
        ]));
        $this->assertFalse(Arr::isAssociative([
            'value', '_key2' => 'value1', 'value'
        ]));
        $this->assertTrue(Arr::isAssociative([
            '_key1' => 'value', '_key2' => 'value1', '_key3' => 'value'
        ]));
    }

    public function testIsIndexed()
    {
        $this->assertFalse(Arr::isIndexed([
            '_key1' => 'value', '_key2' => 'value1', '_key3' => 'value'
        ]));
        $this->assertFalse(Arr::isIndexed([
            'value', '_key2' => 'value1', 'value'
        ]));
        $this->assertTrue(Arr::isIndexed([
            'value', 'value1', 'value'
        ]));
    }

    public function testIsMixed()
    {
        $this->assertFalse(Arr::isMixed([
            '_key1' => 'value', '_key2' => 'value1', '_key3' => 'value'
        ]));
        $this->assertFalse(Arr::isMixed([
            'value', 'value1', 'value'
        ]));
        $this->assertTrue(Arr::isMixed([
            'value', '_key2' => 'value1', 'value'
        ]));
    }

    public function testSet()
    {
        $array = [
            'cars' => [
                'Tom' => [
                    'make' => 'Toyota',
                    'year' => '2011',
                ],
                'Carla' => [
                    'make' => 'Chevrolet',
                    'year' => '2014'
                ]
            ]
        ];

        $expected = '2017';
        Arr::set($array, 'cars.Tom.year', '2017', '.');

        $this->assertEquals($expected, $array['cars']['Tom']['year']);
    }

    public function testToObject()
    {
        $array = ['key_' => 'the value', 'State' => 'complete'];
        $expected = new \stdClass;
        $expected->key_ = $array['key_'];
        $expected->State = $array['State'];
        
        $actual = Arr::toObject($array);

        $this->assertEquals($expected, $actual);
    }
}