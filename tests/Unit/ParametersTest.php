<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\RunTest;
use Tests\TestCase;
use PhpCli\Application;
use PhpCli\Argument;
use PhpCli\Parameters;

final class ParametersTest extends TestCase
{
    public string $binary;
    
    public Parameters $Params;

    public function setUp(): void
    {
        $this->Params = new Parameters();
        $this->binary = dirname(__DIR__).'/harness';
    }

    public function testArgv()
    {
        $expected = "array (\n  0 => '--argv',\n  1 => '--log',\n  2 => '/path/to/file.log',\n)";
        $actual = $this->exe($this->binary, [
            'argv' => true,
            'log' => '/path/to/file.log'
        ]);

        $this->assertEquals($expected, $actual);
    }

    public function testDrop()
    {
        $this->Params->getArguments()->push(new Argument('path'));

        $this->assertNotNull($this->Params->getArguments()->first(function ($item) {
            return $item->name() === 'path';
        }));

        $this->assertTrue($this->Params->drop('path'));
        $this->assertFalse($this->Params->drop('path'));

        $this->assertNull($this->Params->getArguments()->first(function ($item) {
            return $item->name() === 'path';
        }));
    }

    // public function testGetArgument()

    public function testHasArgument()
    {
        $this->assertFalse($this->Params->hasArgument('path'));
        
        $this->Params->getArguments()->push(new Argument('path'));

        $this->assertTrue($this->Params->hasArgument('path'));
    }

    public function testHasRequiredArgument()
    {
        $this->Params->getArguments()->push(new Argument('path', true));

        $this->assertFalse($this->Params->hasRequiredArgument('path'));
        
        $this->Params->getArgument('path')->setValue(__DIR__);
        
        $this->assertTrue($this->Params->hasRequiredArgument('path'));
    }
}