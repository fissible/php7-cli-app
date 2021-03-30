<?php declare(strict_types=1);

namespace Tests\Feature;

use PhpCli\Database\Query;
use PhpCli\Filesystem\File;
use PhpCli\Models\Model;
use Tests\TestCase;

final class ModelTest extends TestCase
{
    use \Tests\UsesDatabase;

    public function testFind()
    {
        $db = $this->setUpDatabase();

        $db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL
        )');

        $result = Query::table('model')->insert(['name' => 'ModelFind']);

        $Model = Model::find(intval($result));

        $this->assertEquals('ModelFind', $Model->name);
    }

    public function testWhere()
    {
        $db = $this->setUpDatabase();

        $db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            color VARCHAR (10) DEFAULT NULL,
            size VARCHAR (10) DEFAULT NULL
        )');

        $result = Query::table('model')->insert(['name' => 'ModelFind']);
        $Model = Model::where('name', 'ModelFind')->first();

        $this->assertEquals((int) $result, $Model->id);

        Query::table('model')->insert([
            ['name' => 'First', 'color' => 'red', 'size' => 'small'],
            ['name' => 'Second', 'color' => 'blue', 'size' => 'medium'],
            ['name' => 'Third', 'color' => 'blue', 'size' => 'large']
        ]);
        $Model = Model::where('color', 'blue')->where('size', 'medium')->first();

        $this->assertEquals('Second', $Model->name);
    }

    public function testGetAttribute()
    {
        $Model = new Model([
            'name' => 'TestModel'
        ]);

        $this->assertEquals('TestModel', $Model->getAttribute('name'));
    }

    public function testGetTable()
    {
        $Model = new Model();
        $this->assertEquals('model', $Model->getTable());
    }

    public function testSetAttribute()
    {
        $Model = new Model(['name' => 'Initial']);
        $Model->setAttribute('name', 'NameTest');

        $this->assertEquals('NameTest', $Model->name);

        $Model->setAttribute('name', 'Initial');

        $this->assertEquals('Initial', $Model->name);
    }

    public function testDelete()
    {
        $db = $this->setUpDatabase();
        $db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL
        )');

        $Model = new Model([
            'name' => 'ModelDelete'
        ], $db);

        $result = Query::table('model')->insert(['name' => 'ModelDelete']);
        $Model->setAttribute('id', (int) $result);

        $this->assertTrue($Model->delete());
    }

    public function testExists()
    {
        $db = $this->setUpDatabase();

        $db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL
        )');

        $Model = new Model([], $db);

        $this->assertFalse($Model->exists());

        $result = Query::table('model')->insert(['name' => 'ModelDelete']);
        $Model = Model::find(intval($result));

        $this->assertTrue($Model->exists());
    }

    public function testHasAttribute()
    {
        $Model = new Model(['name' => 'HasName']);

        $this->assertTrue($Model->hasAttribute('name'));
        $this->assertFalse($Model->hasAttribute('title'));
    }

    public function testIsDirty()
    {
        $Model = new Model(['name' => 'My Name']);
        $Model->setAttribute('name', 'Your Name');

        $this->assertFalse($Model->isDirty());

        $Model->setAttribute('id', 12);
        $Model->setAttribute('name', 'Our Name');

        $this->assertTrue($Model->isDirty());
    }

    public function testInsert()
    {
        $db = $this->setUpDatabase();

        $db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        $Model = new Model(['name' => 'HasName'], $db);

        $this->assertFalse($Model->exists());
        $this->assertTrue($Model->insert());
        $this->assertTrue($Model->exists());

        $this->assertEquals(date('Y-m-d H:i'), $Model->created_at->format('Y-m-d H:i'));
    }

    public function testPrimaryKey()
    {
        $Model = new Model(['name' => 'My Name']);

        $this->assertNull($Model->primaryKey());

        $Model->setAttribute('id', 12);

        $this->assertEquals(12, $Model->primaryKey());
    }

    public function testRefresh()
    {
        $db = $this->setUpDatabase();

        $db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        $Model = new Model(['name' => 'HasAnotherName'], $db);
        $Model->save();

        $this->assertTrue($Model->exists());

        $result = Query::table('model')->update(['name' => 'UpdatedName'], 'updated_at');

        $this->assertTrue($result);
        $this->assertEquals('HasAnotherName', $Model->name);

        $Model->refresh();

        $this->assertEquals('UpdatedName', $Model->name);
    }

    public function testSave()
    {
        $db = $this->setUpDatabase();

        $db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        $Model = new Model(['name' => 'HasAnotherName'], $db);
        
        $this->assertFalse($Model->exists());

        $Model->save();

        $this->assertTrue($Model->exists());

        $Model->name = 'UpdatedName';
        $Model->save();

        $Model = Model::find($Model->id);

        $this->assertEquals('UpdatedName', $Model->name);
    }

    public function testUpdate()
    {
        $db = $this->setUpDatabase();

        $db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        // $Model = new Model(['name' => 'HasAnotherName'], $db);
        $id = intval(Query::table('model')->insert(['name' => 'ModelUpdate']));
        $Model = Model::find($id);

        $this->assertEquals('ModelUpdate', $Model->getAttribute('name'));

        $Model->setAttribute('name', 'AnotherName');
        $Model->update();
        $Model = Model::find($id);

        $this->assertEquals('AnotherName', $Model->getAttribute('name'));
    }

    public function testFloatParam()
    {
        $db = $this->setUpDatabase();

        $db->exec('CREATE TABLE IF NOT EXISTS test_table (
            id INTEGER PRIMARY KEY,
            settlement DATE NOT NULL,
            quantity INTEGER NOT NULL,
            price DECIMAL (10,2)
        )');

        $date = \DateTime::createFromFormat('Ymd', '20200218');
        $settlementDate = $date->getTimestamp();
        $quantity = '3539';
        $price = '27.05';
        $Model = new class([
            'settlement' => $settlementDate,
            'quantity' => (int) $quantity,
            'price' => (float) $price
        ]) extends Model {
            protected static string $table = 'test_table';
            protected static $casts = ['quantity' => 'int'];
            protected array $dates = ['settlement'];
        };

        $this->assertEquals($date, $Model->settlement);
        $this->assertEquals($quantity, $Model->quantity);
        $this->assertTrue(is_int($Model->quantity));
        $this->assertEquals(27.05, $Model->price);
        $this->assertTrue($Model->insert());
        $this->assertEquals($date, $Model->settlement);
        $this->assertEquals(3539, $Model->quantity);
        $this->assertEquals(27.05, $Model->price);
    }

    public function tearDown(): void
    {
        $this->tearDownDatabase();
    }
}