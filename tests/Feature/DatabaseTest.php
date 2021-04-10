<?php declare(strict_types=1);

namespace Tests\Feature;

use PhpCli\Database\Query;
use PhpCli\Database\PaginatedQuery;
use Tests\TestCase;

final class DatabaseTest extends TestCase
{
    use \Tests\UsesDatabase;

    public $db;

    public function setUp(): void
    {
        $this->db = $this->setUpDatabase();

        $this->db->exec('CREATE TABLE IF NOT EXISTS test (
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
        $result = Query::table('test')->insert(['name' => 'First', 'color' => 'red', 'size' => 1]);

        $result = Query::table('test')->insert([
            ['name' => 'Second', 'color' => null, 'size' => 2],
            ['name' => 'Third', 'size' => 2]
        ]);

        $this->assertEquals(2, $result);

        $result = Query::table('test')->insert(['name' =>'Fourth', 'color' => 'Brown', 'size' => 4]);

        $this->assertEquals('4', $result);

        $result = Query::table('test')->where('name', 'Third')->update(['size' => 2]);

        $this->assertTrue($result);

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

        $result = Query::table('test')->insert([
            ['name' => 'Fourth', 'color' => 'Green', 'size' => 3],
            ['name' => 'Fifth', 'color' => 'Green', 'size' => 4],
            ['name' => 'Sixth', 'color' => 'Green', 'size' => 5],
            ['name' => 'Seventh', 'color' => 'Green', 'size' => 6],
            ['name' => 'Eight', 'color' => 'Yellow', 'size' => 7],
            ['name' => 'Ninth', 'color' => 'Green', 'size' => 8],
            ['name' => 'Tenth', 'color' => 'Green', 'size' => 9]
        ]);

        $this->assertEquals(7, $result);

        $rows = Query::table('test')
            ->whereBetween('size', [3, 5])
            ->whereNotIn('name', ['Ninth', 'Tenth'])
            ->orWhere(function (Query $query) {
                $query->whereIn('name', ['First', 'Third']);
            })
            ->orWhere('color', 'Yellow')
            ->get()
            ->column('name');

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

    public function testPaginatedQuery()
    {
        Query::table('test')->insert([
            ['name' => 'First', 'color' => 'red', 'size' => 1],
            ['name' => 'Second', 'color' => null, 'size' => 2],
            ['name' => 'Fourth', 'color' => 'Green', 'size' => 3],
            ['name' => 'Fifth', 'color' => 'Green', 'size' => 4],
            ['name' => 'Sixth', 'color' => 'Green', 'size' => 5],
            ['name' => 'Seventh', 'color' => 'Green', 'size' => 6],
            ['name' => 'Eight', 'color' => 'Yellow', 'size' => 7],
            ['name' => 'Ninth', 'color' => 'Green', 'size' => 8]
        ]);

        $query = PaginatedQuery::table('test', 3);
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

    public function testQueryHaving()
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS albums (
            albumid INTEGER PRIMARY KEY,
            title VARCHAR (20) DEFAULT NULL
        )');
        $this->db->exec('CREATE TABLE IF NOT EXISTS tracks (
            trackid INTEGER PRIMARY KEY,
            name VARCHAR (30) NOT NULL,
            albumid INTEGER NOT NULL,
            composer VARCHAR (20) DEFAULT NULL,
            milliseconds INTEGER DEFAULT 0,
            FOREIGN KEY (albumid) REFERENCES albums(albumid)
        )');

        $albums = [
            ['title' => 'Lost, Season 1'],
            ['title' => 'Lost, Season 2'],
            ['title' => 'Lost, Season 3'],
            ['title' => 'Lost, Season 4'],
            ['title' => 'Battlestar Galactica (Classic), Season 1'],
            ['title' => 'Battlestar Galactica (Classic), Season 2'],
            ['title' => 'Battlestar Galactica (Classic), Season 3'],
            ['title' => 'Battlestar Galactica (Classic), Season 4']
        ];

        $tracks = [
            ['name' => 'Arrival', 'albumid' => 1, 'milliseconds' => 986545],
            ['name' => 'Mystery', 'albumid' => 1, 'milliseconds' => 2345656],
            ['name' => 'Apochrophyl', 'albumid' => 2, 'milliseconds' => 8766554],
            ['name' => 'Annexed Neighbors', 'albumid' => 2, 'milliseconds' => 4456576],
            ['name' => 'Cat in the Cradle', 'albumid' => 3, 'milliseconds' => 8567765],
            ['name' => 'Mystery Unraveled', 'albumid' => 3, 'milliseconds' => 546756854],
            ['name' => 'Following Ephemeral', 'albumid' => 4, 'milliseconds' => 67897343],
            ['name' => 'Inundating Isotopes', 'albumid' => 4, 'milliseconds' => 345459873],
            ['name' => 'Birth of a Nation', 'albumid' => 5, 'milliseconds' => 84758698],
            ['name' => 'Dawn of the Cylons', 'albumid' => 5, 'milliseconds' => 574593457],
            ['name' => 'The Awakening Incident', 'albumid' => 5, 'milliseconds' => 435645674],
            ['name' => 'Breakdown', 'albumid' => 5, 'milliseconds' => 567543456],
            ['name' => 'Attack on Humanity', 'albumid' => 6, 'milliseconds' => 34546457],
            ['name' => 'Yukatan Explosion', 'albumid' => 6, 'milliseconds' => 3459877675],
            ['name' => 'Washburn Breakdown', 'albumid' => 7, 'milliseconds' => 98456793],
            ['name' => 'Insubornation', 'albumid' => 7, 'milliseconds' => 23455466],
            ['name' => 'Latitude South', 'albumid' => 8, 'milliseconds' => 345676457],
            ['name' => 'Freak Storm', 'albumid' => 8, 'milliseconds' => 34564576]
        ];

        Query::table('albums')->insert($albums);
        Query::table('tracks')->insert($tracks);

        $query = Query::table('tracks')
            ->select('tracks.albumid', 'title', 'SUM(milliseconds) AS length')
            ->innerJoin('albums', 'albums.albumid', 'tracks.albumid')
            ->groupBy('tracks.albumid')
            ->having('length', '>', 600000000);

        $rows = $query->get();

        $albumsLengths = [];
        for ($i = 1; $i <= 8; $i++) {
            $albumsLengths[$i] = array_sum(array_column(array_filter($tracks, function ($track) use ($i) {
                return $track['albumid'] === $i;
            }), 'milliseconds'));
        }

        $this->assertEquals(5, $rows->get(0)->albumid);
        $this->assertEquals(6, $rows->get(1)->albumid);
        $this->assertEquals($albumsLengths[5], $rows->get(0)->length);
        $this->assertEquals($albumsLengths[6], $rows->get(1)->length);
    }

    public function testWhereParams()
    {
        $query = Query::table('users')
            ->where('current', '<', Query::raw('max'))
            ->whereIn('status', [Query::raw('prior_status'), Query::raw('next_status')])
            ->whereBetween('current', [Query::raw('max'), Query::raw('min')]);

        $expected = 'SELECT * FROM users WHERE current < max AND status IN (prior_status, next_status) AND current BETWEEN max AND min;';
        $actual = $query.';';

        $this->assertEquals($expected, $actual);
    }

    public function testToString()
    {
        try{
        $query = Query::table('users')->as('a')
            ->select('a.*')
            ->join(Query::table('users')
                ->select('username', 'email', 'COUNT(*)')
                ->groupBy('username', 'email')
                ->having('COUNT(*)', '>', 1),
                'users.username', 'b.username')->as('b')
            ->orderBy('users.email');
        
        $expected = 'SELECT a.* FROM users AS a ';
        $expected .= 'INNER JOIN (';
        $expected .= 'SELECT username, email, COUNT(*) FROM users GROUP BY username, email HAVING COUNT(*) > :HAVING1';
        $expected .= ') AS b ON users.username = b.username ORDER BY users.email ASC;';
        $actual = $query->getSql().';';
        
        $this->assertEquals($expected, $actual);

        $query = Query::select(
                'country.country_name_eng',
                ['calls' => 'SUM(CASE WHEN call.id IS NOT NULL THEN 1 ELSE 0 END)'],
                ['avg_difference' => 'AVG(ISNULL(DATEDIFF(SECOND, call.start_time, call.end_time),0))']
            )
            ->from('country')
            ->leftJoin('city', 'city.country_id', 'country.id')
            ->leftJoin('customer', 'city.id', 'customer.city_id')
            ->leftJoin('call', 'call.customer_id', 'customer.id')
            ->groupBy('country.id', 'country.country_name_eng')
            ->having('AVG(ISNULL(DATEDIFF(SECOND, call.start_time, call.end_time),0))', '>', '(SELECT AVG(DATEDIFF(SECOND, call.start_time, call.end_time)) FROM call)')
            ->orderBy([
                'calls' => 'DESC',
                'country.id' => 'ASC'
            ]);

        $expected = preg_replace("/\s+/", ' ', 'SELECT 
                country.country_name_eng,
                SUM(CASE WHEN call.id IS NOT NULL THEN 1 ELSE 0 END) AS calls,
                AVG(ISNULL(DATEDIFF(SECOND, call.start_time, call.end_time),0)) AS avg_difference
            FROM country 
            LEFT JOIN city ON city.country_id = country.id
            LEFT JOIN customer ON city.id = customer.city_id
            LEFT JOIN call ON call.customer_id = customer.id
            GROUP BY 
                country.id,
                country.country_name_eng
            HAVING AVG(ISNULL(DATEDIFF(SECOND, call.start_time, call.end_time),0)) > (SELECT AVG(DATEDIFF(SECOND, call.start_time, call.end_time)) FROM call)
            ORDER BY calls DESC, country.id ASC;'
        );
        $actual = $query->getSql().';';
        
        $this->assertEquals($expected, $actual);
        } catch (\Exception $e) {
            var_dump($e);
        }
    }

    public function tearDown(): void
    {
        $this->tearDownDatabase();
    }
}