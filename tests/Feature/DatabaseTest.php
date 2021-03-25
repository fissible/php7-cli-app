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
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');
    }

    public function testQuery()
    {
        $result = Query::table('test')->insert([
            ['name' => 'First'],
            ['name' => 'Second']
        ]);

        $this->assertTrue($result);

        $result = Query::table('test')->insert(['name' => 'Third']);

        $this->assertEquals('3', $result);

        // var_dump(Query::table('test')->get());


        $row = Query::table('test')
            ->where('name', 'Second')
            ->first();
        
        $this->assertEquals('Second', $row->name);

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