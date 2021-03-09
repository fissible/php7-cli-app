<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use PhpCli\Option;
use PhpCli\Options;
use PhpCli\Parameters;
use PhpCli\Events\Event;
use PhpCli\Events\AddParameterEvent;
use PhpCli\Events\DropParameterEvent;

class OptionsTest extends TestCase
{
    public $Options;

    public $Parameters;

    public function setUp(): void
    {
        $this->Parameters = new Parameters();
        $this->Options = $this->Parameters->getOptions();
    }

    public function testPushPull()
    {
        $Option = new Option('testing');
        $Pulled = new \PhpCli\Collection();

        $this->assertEquals(0, $this->Options->count());
        $this->assertEmpty($this->Parameters->options);

        $count = $this->Options->push($Option);

        $this->assertEquals(1, $count);
        $this->assertEquals(1, $this->Options->count());
        $this->assertNotNull($this->Parameters->options);


        $Pulled = $this->Options->pull(function (Option $Opt) {
            return $Opt->name() == 'testing';
        });

        $this->assertEquals(1, $Pulled->count());
        $this->assertEquals(0, $this->Options->count());
        $this->assertEquals($Option, $Pulled->first());
    }
}