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
            size INTEGER (2) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT (strftime(\'%s\',\'now\')),
            updated_at TIMESTAMP
        )');
    }

    public function testQuery()
    {
        $result = Query::table('test')->insert([
            ['name' => 'First', 'color' => 'red', 'size' => 1],
            ['name' => 'Second', 'color' => null, 'size' => 2]
        ]);

        $this->assertTrue($result);

        $result = Query::table('test')->insert(['name' => 'Third', 'size' => 2]);

        $this->assertEquals('3', $result);

        $row = Query::table('test')
            ->where('name', 'Second')
            ->first();

        $this->assertEquals('Second', $row->name);

        $count = Query::table('test')
            ->select('name', 'color')
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

        Query::table('test')->insert(['name' => 'Fourth', 'color' => 'Green', 'size' => 3]);
        Query::table('test')->insert(['name' => 'Fifth', 'color' => 'Green', 'size' => 4]);
        Query::table('test')->insert(['name' => 'Sixth', 'color' => 'Green', 'size' => 5]);
        Query::table('test')->insert(['name' => 'Seventh', 'color' => 'Green', 'size' => 6]);
        Query::table('test')->insert(['name' => 'Eight', 'color' => 'Yellow', 'size' => 7]);
        Query::table('test')->insert(['name' => 'Ninth', 'color' => 'Green', 'size' => 8]);
        Query::table('test')->insert(['name' => 'Tenth', 'color' => 'Green', 'size' => 9]);

        print("\n".
            Query::table('test')
                ->whereBetween('size', [3, 5])
                ->whereNotIn('name', ['Ninth', 'Tenth'])
                ->orWhere(function (Query $query) {
                    $query->whereIn('name', ['First', 'Third']);
                })
                ->orWhere('color', 'Yellow')
                ->compileQuery()
        ."\n");

        $rows = Query::table('test')
            ->whereBetween('size', [3, 5])
            ->whereNotIn('name', ['Ninth', 'Tenth'])
            ->orWhere(function (Query $query) {
                $query->whereIn('name', ['First', 'Third']);
            })
            ->orWhere('color', 'Yellow')
            ->get()
            ->column('name');

        print_r($rows);

        var_dump((new Query)->exe(
            "SELECT * FROM `test` WHERE 
            size BETWEEN 3 AND 5 
            AND name NOT IN ('Ninth', 'Tenth') 
            OR (name IN ('First', 'Third')) 
            OR color = 'Yellow'
            ")->fetchAll(\PDO::FETCH_OBJ)
        );

        $this->assertTrue($rows->contains('First'));
        $this->assertTrue($rows->contains('Third'));
        $this->assertTrue($rows->contains('Fourth'));
        $this->assertTrue($rows->contains('Fifth'));
        $this->assertTrue($rows->contains('Sixth'));
        $this->assertFalse($rows->contains('Seventh'));
        $this->assertTrue($rows->contains('Eight'));
        $this->assertFalse($rows->contains('Ninth'));
        $this->assertFalse($rows->contains('Tenth'));
    }

    public function testQueryExe()
    {
        Query::table('test')->insert([
            ['name' => 'First', 'color' => 'red'],
            ['name' => 'Second', 'color' => null]
        ]);

        $statement = (new Query())->exe('SELECT COUNT(*) FROM test');
        $count = (int) $statement->fetchColumn();

        $this->assertEquals(2, $count);

        $id = (new Query())->exe('INSERT INTO test (name, color) VALUES (\'Third\', \'blue\')');

        $this->assertTrue(is_numeric($id));

        $statement = (new Query())->exe('SELECT name FROM test WHERE color IS NOT NULL');
        $result = $statement->fetch(\PDO::FETCH_OBJ);

        $this->assertEquals('First', $result->name);

        $updated = (new Query())->exe('UPDATE test SET color = \'green\' WHERE name = \'Third\'');

        $this->assertTrue($updated);

        $deleted = (new Query())->exe('DELETE FROM test WHERE color = \'green\'');

        $this->assertTrue($deleted);

        $statement = (new Query())->exe('SELECT COUNT(*) FROM test WHERE name = \'Third\'');
        $count = (int) $statement->fetchColumn();

        $this->assertEquals(0, $count);
    }

    public function tearDown(): void
    {
        $this->tearDownDatabase();
    }
}