<?php declare(strict_types=1);

namespace Tests\Feature;

use PhpCli\Config\Json;
use Tests\TestCase;

final class ConfigTest extends TestCase
{
    public $config;

    protected function setUp(): void
    {
        $this->config = new Json(sprintf('%s/config.json', __DIR__));
    }

    public function testSetGet()
    {
        $this->config->set('tree', 'green');

        $this->assertEquals('green', $this->config->get('tree'));

        $this->config->set('tree', 'brow');

        $this->assertEquals('brow', $this->config->get('tree'));

        $this->config->set('car.paint', 'red');

        $this->assertEquals('red', $this->config->get('car.paint'));

        $this->config->set('car.paint', 'blue');

        $this->assertEquals('blue', $this->config->get('car.paint'));
    }

    public function testGetDataTHroughJsonPointer()
    {
        $restConfig = new Json(dirname(__DIR__).'/restConfig.json');
        $expected = 'eCFR API';
        $actual = $restConfig->get('services.eCFR.info.title');

        $this->assertEquals($expected, $actual);
    }

    protected function tearDown(): void
    {
        if ($this->config->exists()) {
            $this->config->delete();
        }
    }
}