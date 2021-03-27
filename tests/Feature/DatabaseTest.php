<?php declare(strict_types=1);

namespace Tests\Feature;

use PhpCli\Database\Query;
use Tests\TestCase;

final class DatabaseTest extends TestCase
{
    use \Tests\UsesDatabase;

    public function setUp(): void
    {
        $db = $this->setUpDatabase();

        $db->exec('CREATE TABLE IF NOT EXISTS test (
            id INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            color VARCHAR (10) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');
    }

    public function testQuery()
    {
        $result = Query::table('test')->insert([
            ['name' => 'First', 'color' => 'red'],
            ['name' => 'Second', 'color' => null]
        ]);

        $this->assertTrue($result);

        $result = Query::table('test')->insert(['name' => 'Third']);

        $this->assertEquals('3', $result);

        $row = Query::table('test')
            ->where('name', 'Second')
            ->first();

        $this->assertEquals('Second', $row->name);

        $count = Query::table('test')
            ->whereIn('name', ['Second', 'Third'])
            ->count();
        
        $this->assertEquals(2, $count);

        $rows = Query::table('test')
            ->whereIn('name', ['Second', 'Third'])
            ->get()
            ->column('name');

        $this->assertFalse($rows->contains('First'));
        $this->assertTrue($rows->contains('Second'));
        $this->assertTrue($rows->contains('Third'));

        $rows = Query::table('test')
            ->where('color', null)
            ->get()
            ->column('name');

        $this->assertFalse($rows->contains('First'));
        $this->assertTrue($rows->contains('Second'));
        $this->assertTrue($rows->contains('Third'));

        $rows = Query::table('test')
            ->where('name', 'Third')
            ->orWhere(function (Query $query) {
                $query->where('name', '!=', 'First');
            })
            ->get()
            ->column('name');

        $this->assertFalse($rows->contains('First'));
        $this->assertTrue($rows->contains('Second'));
        $this->assertTrue($rows->contains('Third'));

        $rows = Query::table('test')
            ->whereBetween('id', [2, 3])
            ->get()
            ->column('name');

        $this->assertFalse($rows->contains('First'));
        $this->assertTrue($rows->contains('Second'));
        $this->assertTrue($rows->contains('Third'));

        $result = Query::table('test')
            ->where('name', 'Second')
            ->delete();

        $this->assertTrue($result);

        $row = Query::table('test')
            ->where('name', 'Second')
            ->first();
        
        $this->assertNull($row);
    }

    public function tearDown(): void
    {
        $this->tearDownDatabase();
    }
}