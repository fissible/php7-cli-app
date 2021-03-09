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