<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use PhpCli\Application;

final class ApplicationTest extends TestCase
{
    public $app;

    public function setUp(): void
    {
        $this->app = new Application();
    }

    public function testHash()
    {
        $data = ['one', 2, ['t', 'h', 'r', 'e', 'e']];
        $expected = sha1(var_export($data, true));
        $actual = Application::hash(...$data);

        $this->assertEquals($expected, $actual);
    }

    public function testCache()
    {
        $runs = 0;
        $data = ['one', 2, ['t', 'h', 'r', 'e', 'e']];
        $hash = null;
        $callback = function ($one, $two, $three) use (&$runs) {
            $runs++;
            return str_repeat($one, $two).implode($three);
        };

        $value = Application::cacheResult($callback, ...$data);

        $this->assertEquals('oneonethree', $value);
        $this->assertEquals(1, $runs);

        $value = Application::cacheResult($callback, ...$data);

        $this->assertEquals('oneonethree', $value);
        $this->assertEquals(1, $runs);

        $data = ['two', 2, ['t', 'h', 'r', 'e', 'e']];
        $value = Application::cacheResult($callback, ...$data);

        $this->assertEquals('twotwothree', $value);
        $this->assertEquals(2, $runs);

        $value = Application::cacheResult($callback, ...$data);

        $this->assertEquals('twotwothree', $value);
        $this->assertEquals(2, $runs);
    }

    public function testTable()
    {
        $expected = '
┌──────────────┬───────────┐
│ Name         │ Value     │
├──────────────┼───────────┤
│ Color        │ blue      │
│ Transmission │ automatic │
│ Drive        │ FWD       │
│ Cupholders   │ 6         │
└──────────────┴───────────┘
';
        $Table = $this->app->table([
            'Name', 'Value'
        ], [
            ['Color', 'blue'],
            ['Transmission', 'automatic'],
            ['Drive', 'FWD'],
            ['Cupholders', 6]
        ]);

        $renderedArray = $Table->render();
        $actual = "\n" . implode($renderedArray);

        $this->assertEquals($expected, $actual);
    }
}