<?php declare(strict_types=1);

use PhpCli\Str;
use PHPUnit\Framework\TestCase;

class StrTest extends TestCase {

    public function testAfter()
    {
        $val = Str::after('hash val (main)', '(');

        $this->assertEquals('main)', $val);

        $val = Str::after('hash val (main)', '#');

        $this->assertEquals('hash val (main)', $val);

        $val = Str::after('/* get comment */', '*', 2);

        $this->assertEquals('/', $val);
    }

    public function testBefore()
    {
        $val = Str::before('hash val (main)', '(');

        $this->assertEquals('hash val ', $val);

        $val = Str::before('hash val (main)', '#');

        $this->assertEquals('hash val (main)', $val);

        $val = Str::before('/* get comment */', '*', 2);

        $this->assertEquals('/* get comment ', $val);
    }

    public function testCapture()
    {
        $val = Str::capture('test(arg)', '(', ')');

        $this->assertEquals('arg', $val);

        $val = Str::capture('test(arg)', '[', ']');

        $this->assertEquals('', $val);

        $val = Str::capture('+5 -7', '+', ' ');

        $this->assertEquals('5', $val);

        $val = Str::capture('+5 -7', null, ' ');

        $this->assertEquals('+5', $val);

        $val = Str::capture('+5 -7', '-', ' ');

        $this->assertEquals('7', $val);

        $val = Str::capture('+5 -7', '-');

        $this->assertEquals('7', $val);
    }

    public function testContains()
    {
        $this->assertFalse(Str::contains('chest', 'treasure'));
        $this->assertFalse(Str::contains('chest', 'a'));
        $this->assertTrue(Str::contains('chest', 'c'));
        $this->assertTrue(Str::contains('chest', 'hes'));
    }

    public function testEndsWith()
    {
        $this->assertFalse(Str::endsWith('Treebeard', 'T'));
        $this->assertTrue(Str::endsWith('Treebeard', 'd'));
    }

    public function testIsQuoted()
    {
        $this->assertTrue(Str::isQuoted('"the string"'));
        $this->assertTrue(Str::isQuoted("'the string'"));
        $this->assertTrue(Str::isQuoted('"the string"', '"'));
        $this->assertTrue(Str::isQuoted("'the string'", "'"));
        $this->assertTrue(Str::isQuoted('Athe stringA', 'A'));
        $this->assertTrue(Str::isQuoted("\"the string\""));
        $this->assertFalse(Str::isQuoted('\"the string\"'));
        $this->assertFalse(Str::isQuoted('"the string"', "'"));
        $this->assertFalse(Str::isQuoted("'the string'", '"'));
        $this->assertFalse(Str::isQuoted("'the string"));
    }

    public function testPrune()
    {
        $expected = 'Treebeard';
        $actual = Str::lprune('Treebeard', 'e');
        
        $this->assertEquals($expected, $actual);

        $expected = 'bracadabra';
        $actual = Str::lprune('abracadabra', 'a');
        
        $this->assertEquals($expected, $actual);

        $expected = 'bracadabr';
        $actual = Str::prune('abracadabra', 'a');
        
        $this->assertEquals($expected, $actual);

        $expected = 'abracadabra';
        $actual = Str::prune('aabracadabraa', 'a');
        
        $this->assertEquals($expected, $actual);

        $expected = 'abracadabr';
        $actual = Str::rprune('abracadabra', 'a');
        
        $this->assertEquals($expected, $actual);

        $expected = 'A line by itself.';
        $actual = Str::rprune("A line by itself.\n", "\n");
        
        $this->assertEquals($expected, $actual);

        $expected = "A line by itself.\n";
        $actual = Str::prune("A line by itself.\n\n", "\n");
        
        $this->assertEquals($expected, $actual);

        $expected = "A line by itself.\n";
        $actual = Str::prune("A line by itself.\n\n\n", "\n\n");
        
        $this->assertEquals($expected, $actual);
    }

    public function testStartsWith()
    {
        $this->assertFalse(Str::startsWith('Treebeard', 'd'));
        $this->assertTrue(Str::startsWith('Treebeard', 'T'));
    }
}