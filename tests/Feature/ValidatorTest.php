<?php declare(strict_types=1);

namespace Tests\Feature;

use PhpCli\Collection;
use PhpCli\Exceptions\ValidationException;
use PhpCli\Validation\Validator;
use Tests\TestCase;

final class ValidatorTest extends TestCase
{
    public $v;

    public function setUp(): void
    {
        $this->v = new Validator([
            'name' => ['required', 'regex:/B{1}o{1}b{1}/'],
            'date' => ['date:Y-m-d']
        ], [
            'name.required' => 'Name is required'
        ]);
    }

    public function testErrors()
    {
        $this->v->fails(['name' => '', 'date' => '2014-1-10']);
        $nameErrorMessages = ['Name is required'];
        $dateErrorMessages = ['The "date" field format is invalid.'];
        $expected = new Collection([
            'name' => $nameErrorMessages,
            'date' => $dateErrorMessages
        ]);
        $actual = $this->v->errors();

        $this->assertEquals($expected, $actual);
        $this->assertEquals($nameErrorMessages, $this->v->errors()->get('name'));
        $this->assertEquals($dateErrorMessages, $this->v->errors()->get('date'));
    }

    public function testFails()
    {
        $this->assertTrue($this->v->fails(['name' => '']));
        $this->assertTrue($this->v->fails(['name' => null]));
        $this->assertTrue($this->v->fails(['name' => 'Rob']));
        $this->assertFalse($this->v->fails(['name' => 'Bob']));
    }

    public function testPasses()
    {
        $this->assertTrue($this->v->passes([
            'name' => 'Bob',
            'date' => '2012-01-10'
        ]));
        $this->assertFalse($this->v->passes([
            'name' => 'Rob',
            'date' => '2012-01-10'
        ]));
        $this->assertFalse($this->v->passes([
            'name' => 'Bob',
            'date' => '2012-01-1'
        ]));
    }

    public function testMessages()
    {
        $expected = [
            'name.required' => 'Name is required'
        ];
        $actual = $this->v->messages();

        $this->assertEquals($expected, $actual);
    }

    public function testValidatePass()
    {
        $expected = ['name' => 'Bob'];
        $actual = $this->v->validate(['name' => 'Bob']);

        $this->assertEquals($expected, $actual);
    }

    public function testValidateFail()
    {
        $this->expectException(ValidationException::class);

        $this->v->validate(['name' => '']);
    }


}