<?php declare(strict_types=1);

namespace Tests\Unit;

use PhpCli\Config;
use PhpCli\Filesystem\File;
use Tests\TestCase;

final class ConfigTest extends TestCase
{

    public $path;

    public function setUp(): void
    {
        $this->path = __DIR__.'/testConfig.json';
    }

    public function testExists()
    {
        $Config = new Config();

        $this->assertNull($Config->exists());

        $Config->setFile($this->path);

        $this->assertFalse($Config->exists());

        $Config->test = 'value';
        $Config->persist();

        $this->assertTrue($Config->exists());
        $this->assertTrue($Config->getFile()->delete());
        $this->assertFalse($Config->exists());
    }

    public function testGetFile()
    {
        $Config = new Config($this->path);
        $File = $Config->getFile();

        $this->assertInstanceOf(File::class, $File);
    }

    public function testPersist()
    {
        $Config = new Config($this->path);
        $Config->test = 'value';

        $this->assertFalse($Config->exists());

        $bytes = $Config->persist();

        $this->assertGreaterThan(0, $bytes);
        $this->assertTrue($Config->exists());
        $this->assertTrue($Config->getFile()->delete());
        $this->assertFalse($Config->exists());
    }

    public function testSetFile()
    {
        $Config = new Config();
        $Config->setFile($this->path);

        $this->assertEquals($this->path, $Config->getFile()->getPath());
    }

    public function testGetSet()
    {
        $Config = new Config();
        $value = '234frsf';
        $Config->test = $value;

        $this->assertEquals($value, $Config->test);
    }
}