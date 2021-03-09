<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use PhpCli\Option;

final class OptionTest extends TestCase
{
    public function testAliases()
    {
        $Option = new Option('t|test');
        $expected = ['test'];
        $actual = $Option->aliases();

        $this->assertEquals($expected, $actual);
    }

    public function testEmpty()
    {
        $Option = new Option('t');

        $this->assertTrue($Option->empty());

        $Option = new Option('t', true, '', time());
        
        $this->assertFalse($Option->empty());
    }

    public function testEquals()
    {
        $expected = time();
        $Option = new Option('t', true, '', $expected);

        $this->assertTrue($Option->equals($expected));
    }

    public function testName()
    {
        $expected = 'option_name';
        $Option = new Option($expected);
        $actual = $Option->name();

        $this->assertEquals($expected, $actual);
    }

    public function testDescription()
    {
        $expected = 'option description';
        $Option = new Option('t', false, $expected);
        $actual = $Option->description();

        $this->assertEquals($expected, $actual);
    }

    public function testValue()
    {
        $expected = time();
        $Option = new Option('t', true, '', $expected);
        $actual = $Option->value();

        $this->assertEquals($expected, $actual);
    }

    public function testIs()
    {
        $Option = new Option('t|test');

        $this->assertTrue($Option->is('t'));
        $this->assertTrue($Option->is('test'));
        $this->assertFalse($Option->is('description'));
    }

    public function testIsFlag()
    {
        $Option1 = new Option('one');
        $Option2 = new Option('two', null);
        $Option3 = new Option('thr', true);
        $Option4 = new Option('fou', false);

        $this->assertTrue($Option1->isFlag());
        $this->assertTrue($Option2->isFlag());
        $this->assertFalse($Option3->isFlag());
        $this->assertFalse($Option4->isFlag());
    }

    public function testIsLong()
    {
        $Option1 = new Option('one');
        $Option2 = new Option('two');
        $Option3 = new Option('t');

        $this->assertTrue($Option1->isLong());
        $this->assertTrue($Option2->isLong());
        $this->assertFalse($Option3->isLong());
    }

    public function testIsShort()
    {
        $Option1 = new Option('o');
        $Option2 = new Option('t');
        $Option3 = new Option('three');

        $this->assertTrue($Option1->isShort());
        $this->assertTrue($Option2->isShort());
        $this->assertFalse($Option3->isShort());
    }

    public function testIsRequired()
    {
        $Option1 = new Option('one');
        $Option2 = new Option('two', null);
        $Option3 = new Option('thr', true);
        $Option4 = new Option('fou', false);

        $this->assertFalse($Option1->isRequired());
        $this->assertFalse($Option2->isRequired());
        $this->assertTrue($Option3->isRequired());
        $this->assertFalse($Option4->isRequired());
    }

    public function testIsOptional()
    {
        $Option1 = new Option('one');
        $Option2 = new Option('two', null);
        $Option3 = new Option('thr', true);
        $Option4 = new Option('fou', false);

        $this->assertFalse($Option1->isOptional());
        $this->assertFalse($Option2->isOptional());
        $this->assertFalse($Option3->isOptional());
        $this->assertTrue($Option4->isOptional());
    }

    public function testSetAliases()
    {
        $Option = new Option('t');
        $expected = ['test', 'unit'];
        $Option->setAliases(['test', 'unit']);
        $actual = $Option->aliases();

        $this->assertEquals($expected, $actual);

        $expected = ['feature'];
        $Option->setAliases('feature');
        $actual = $Option->aliases();

        $this->assertEquals($expected, $actual);
    }

    public function testSetName()
    {
        $Option = new Option('t|test');
        $expectedName = 't';
        $expectedAliases = ['test'];
        $actualName = $Option->name();
        $actualAliases = $Option->aliases();

        $this->assertEquals($expectedName, $actualName);
        $this->assertEquals($expectedAliases, $actualAliases);
    }

    public function testSetValue()
    {
        $Option = new Option('t|test');

        $this->assertNull($Option->value());

        $expected = time();
        $Option->setValue($expected);
        $actual = $Option->value();

        $this->assertEquals($expected, $actual);
    }

    public function testPushValue()
    {
        $Option = new Option('t');

        $this->assertNull($Option->value());

        $time1 = time();
        $Option->pushValue($time1);
        $actual = $Option->value();

        $this->assertEquals($time1, $actual);

        $time2 = time();
        $expected = [$time1, $time2];
        $Option->pushValue($time2);
        $actual = $Option->value();

        $this->assertEquals($expected, $actual);
    }

    public function testGetCliFlagsString()
    {
        $Option = new Option('v|verbose');
        $expected = '-v, --verbose';
        $actual = $Option->getCliFlagsString();

        $this->assertEquals($expected, $actual);

        $Option = new Option('t|test', true);
        $expected = '-t, --test <value>';
        $actual = $Option->getCliFlagsString();

        $this->assertEquals($expected, $actual);

        $Option = new Option('t|test', 'file');
        $expected = '-t, --test <file>';
        $actual = $Option->getCliFlagsString();

        $this->assertEquals($expected, $actual);
    }

    public function testGetOptionsString()
    {
        $Option = new Option('test');
        $expected = 'test';
        $actual = $Option->getOptionString();

        $this->assertEquals($expected, $actual);

        $Option = new Option('test', true);
        $expected = 'test:';
        $actual = $Option->getOptionString();

        $this->assertEquals($expected, $actual);

        $Option = new Option('test', false);
        $expected = 'test::';
        $actual = $Option->getOptionString();

        $this->assertEquals($expected, $actual);

        $Option = new Option('test', false);
        $expected = 'WHY::'; // srsly, why?
        $actual = $Option->getOptionString('WHY');

        $this->assertEquals($expected, $actual);
    }

    public function testGetRequirementString()
    {
        $Option = new Option('test');
        $expected = '';
        $actual = $Option->getRequirementString();

        $this->assertEquals($expected, $actual);

        $Option = new Option('test', true);
        $expected = ':';
        $actual = $Option->getRequirementString();

        $this->assertEquals($expected, $actual);

        $Option = new Option('test', false);
        $expected = '::';
        $actual = $Option->getRequirementString();

        $this->assertEquals($expected, $actual);
    }

    public function testGetOptionString()
    {
        $Option = new Option('t|test');
        $expected = [
            't', ['test']
        ];
        $actual = $Option->getOptionStrings();

        $this->assertEquals($expected, $actual);

        $Option = new Option('t|test', false, 'description text');
        $expected = [
            't::', ['test::']
        ];
        $actual = $Option->getOptionStrings();

        $this->assertEquals($expected, $actual);

        $Option = new Option('t|test', true);
        $expected = [
            't:', ['test:']
        ];
        $actual = $Option->getOptionStrings();

        $this->assertEquals($expected, $actual);
    }

    public function testToString()
    {
        $expected = '_value_';
        $Option = new Option('t|test', true, '', $expected);
        $actual = $Option . '';

        $this->assertEquals($expected, $actual);
    }
}