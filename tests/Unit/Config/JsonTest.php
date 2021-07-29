<?php declare(strict_types=1);

namespace Tests\Unit\Config;

use PhpCli\Arr;
use PhpCli\Config\JsonPointer;
use Tests\Sauron as JsonConfig;
use Tests\TestCase;

final class JsonTest extends TestCase
{

    public $path;

    public function setUp(): void
    {
        $this->path = dirname(dirname(__DIR__)).'/testConfig.json';

        JsonConfig::sees(\PhpCli\Config\Json::class);
    }

    public function testExists()
    {
        $Config = new JsonConfig($this->path);

        $this->assertFalse($Config->exists());

        $Config->write(['test' => 'value']);

        $this->assertTrue($Config->exists());
        $this->assertTrue($Config->delete());
        $this->assertFalse($Config->exists());
    }

    public function testPersist()
    {
        $Config = new JsonConfig($this->path);

        $this->assertFalse($Config->exists());

        $bytes = $Config->write(['test' => 'value']);

        $this->assertGreaterThan(0, $bytes);

        $Config = new JsonConfig($this->path);

        $this->assertTrue($Config->exists());
        $this->assertEquals('value', $Config->test);
        $this->assertTrue($Config->delete());
        $this->assertFalse($Config->exists());
    }

    public function testGetSet()
    {
        $Config = new JsonConfig($this->path);
        $value = '234frsf';
        $Config->test = $value;

        $this->assertEquals($value, $Config->test);

        $database = [
            'driver' => 'postgres',
            'username' => 'root',
            'password' => '123'
        ];
        $Config->database = $database;

        $this->assertEquals($database['driver'], $Config->database->driver);
        $this->assertEquals($database['driver'], $Config->get('database.driver'));
        $this->assertEquals(Arr::toObject($database), $Config->get('database'));
        $this->assertEquals(Arr::toObject($database), $Config->database);

        $Config->set('database.password', 'abc');

        $this->assertEquals('abc', $Config->get('database.password'));

        $Config->set('database', [
            'driver' => 'mysql',
            'username' => 'user',
            'password' => '123'
        ]);

        $this->assertEquals('mysql', $Config->get('database.driver'));
        $this->assertEquals('123', $Config->get('database.password'));
    }

    public function testHas()
    {
        $Config = new JsonConfig($this->path);
        $value = '234frsf';
        $Config->test = $value;

        $this->assertTrue($Config->has('test'));
        $this->assertFalse($Config->has('fraggle'));

        $ref = ['$ref' => 'ApiSchema/eCFR.json#'];
        $Config->schema = $ref;
        $Config->write();

        $this->assertTrue($Config->has('schema'));
        $this->assertTrue($Config->has('schema.openapi'));
        $this->assertFalse($Config->has('schema.fraggle'));
        $this->assertTrue($Config->delete());
        $this->assertFalse($Config->exists());
    }

    public function testResolveRefConfigFile()
    {
        $Config = new JsonConfig($this->path);
        $ref = ['$ref' => 'ApiSchema/eCFR.json#'];
        $Pointer = new JsonPointer(json_decode(json_encode($ref)));
        $Config->write(['schema' => ['$ref' => 'ApiSchema/eCFR.json#']]);

        $refConfig = $Config->resolveRefConfigFile($Pointer);

        $this->assertInstanceOf(\PhpCli\Config\Json::class, $refConfig);
        $this->assertEquals('3.0.0', $refConfig->openapi);
        $this->assertEquals('3.0.0', $Config->schema->openapi);

        $this->assertTrue($Config->delete());
        $this->assertFalse($Config->exists());
    }

    public function tearDown(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }
}