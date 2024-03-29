<?php declare(strict_types=1);

namespace Tests\Feature;

use PhpCli\Database\Query;
use PhpCli\Database\PaginatedQuery;
use PhpCli\Filesystem\File;
use PhpCli\Models\Model;
use Tests\TestCase;

final class ModelTest extends TestCase
{
    use \Tests\UsesDatabase;

    public function testFind()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL
        )');

        $result = Query::table('model')->insert(['name' => 'ModelFind']);
        $Model = Model::find(intval($result));

        $this->assertEquals('ModelFind', $Model->name);
    }

    public function testWhere()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
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
        $Model = new Model(['name' => 'TestModel']);

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
        $this->setUpDatabase();
        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL
        )');

        $Model = new Model([
            'name' => 'ModelDelete'
        ]);

        $result = Query::table('model')->insert(['name' => 'ModelDelete']);
        $Model->setAttribute('id', (int) $result);

        $this->assertTrue($Model->delete());
    }

    public function testExists()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL
        )');

        $Model = new Model();

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

    public function testCreate()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        $Model = Model::create(['name' => 'HasName']);

        $this->assertTrue($Model->exists());
        $this->assertTrue(is_int($Model->id));

        $this->assertEquals(date('Y-m-d H:i'), $Model->created_at->format('Y-m-d H:i'));
    }

    public function testInsert()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        $Model = new Model(['name' => 'HasName']);

        $this->assertFalse($Model->exists());
        $this->assertTrue($Model->insert());
        $this->assertTrue($Model->exists());
        $this->assertTrue(is_int($Model->id));

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
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        $Model = new Model(['name' => 'HasAnotherName']);
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
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        $Model = new Model(['name' => 'HasAnotherName']);
        
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
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS model (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');

        // $Model = new Model(['name' => 'HasAnotherName']);
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
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS test_table (
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
            protected const CREATED_FIELD = null;
            protected const UPDATED_FIELD = null;
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

    public function testPaginatedQuery()
    {
        $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS test_table (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            color VARCHAR (10),
            size VARCHAR (10) NOT NULL
        )');

        Query::table('test_table')->insert([
            ['name' => 'First', 'color' => 'red', 'size' => 1],
            ['name' => 'Second', 'color' => null, 'size' => 2],
            ['name' => 'Fourth', 'color' => 'Green', 'size' => 3],
            ['name' => 'Fifth', 'color' => 'Green', 'size' => 4]
        ]);

        (new TestModel)->insert([
            ['name' => 'Sixth', 'color' => 'Green', 'size' => 5],
            ['name' => 'Seventh', 'color' => 'Green', 'size' => 6],
            ['name' => 'Eight', 'color' => 'Yellow', 'size' => 7],
            ['name' => 'Ninth', 'color' => 'Green', 'size' => 8]
        ]);

        $query = new PaginatedQuery(TestModel::class, 3);
        $query->where('color', 'Green');

        $rows = $query->get(1);

        $this->assertEquals(5, $query->total());
        $this->assertEquals(2, $query->pages());
        $this->assertCount(3, $rows);
        $this->assertTrue($rows->column('name')->contains('Fourth'));
        $this->assertTrue($rows->column('name')->contains('Fifth'));
        $this->assertTrue($rows->column('name')->contains('Sixth'));

        $rows = $query->get(2);

        $this->assertCount(2, $rows);
        $this->assertTrue($rows->column('name')->contains('Seventh'));
        $this->assertTrue($rows->column('name')->contains('Ninth'));
    }

    public function testJsonSerialization()
    {
        $date = \DateTime::createFromFormat('Ymd', '20200218');
        $formattedDate = $date->format('Y-m-d\TH:i:sP');
        $settlementDate = $date->getTimestamp();
        $quantity = '3539';
        $price = '27.05';
        $Model = new TestModel([
            'settlement' => $settlementDate,
            'quantity' => (int) $quantity,
            'price' => (float) $price
        ]);

        $expected = '{"settlement":"'.$formattedDate.'","quantity":3539,"price":27.05}';
        $actual = json_encode($Model);

        $this->assertEquals($expected, $actual);
    }

    public function testSerialization()
    {
        $date = \DateTime::createFromFormat('Ymd', '20200218');
        $settlementDate = $date->getTimestamp();
        $quantity = '3539';
        $price = '27.05';
        $Model = new TestModel([
            'settlement' => $settlementDate,
            'quantity' => (int) $quantity,
            'price' => (float) $price
        ]);

        $ser = serialize($Model);
        $newModel = unserialize($ser);

        $this->assertEquals($Model->getAttributes(), $newModel->getAttributes());
    }

    public function tearDown(): void
    {
        $this->tearDownDatabase();
    }
}