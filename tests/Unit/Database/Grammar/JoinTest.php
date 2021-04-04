<?php declare(strict_types=1);

namespace Tests\Unit\Database\Grammar;

use PhpCli\Database\Grammar\Join;
use PhpCli\Database\Query;
use Tests\TestCase;

final class JoinTest extends TestCase
{
    public function testCompileException()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('JOIN missing required criteria.');

        $join = new Join(new Query(), 'LEFT', 'users');
        $join->compile();
    }

    public function testCompile()
    {
        $join = new Join(new Query(), Join::TYPE_LEFT, 'users');
        $join->on('users.id', 'primary_table.user_id');

        $expected = 'LEFT JOIN users ON users.id = primary_table.user_id';
        $actual = $join->compile();

        $this->assertEquals($expected, $actual);

        $join = new Join(new Query(), 'LEFT', 'users');
        $join->as('u');
        $join->on('u.id', 'primary_table.user_id');

        $expected = 'LEFT JOIN users AS u ON u.id = primary_table.user_id';
        $actual = $join->compile();

        $this->assertEquals($expected, $actual);
    }

    public function testComplexCompile()
    {
        $subQuery = Query::select('id', 'username')->from('users')->where('email', 'ILIKE', '%@yahoo.com');
        $join = new Join(new Query(), Join::TYPE_INNER, $subQuery);
        $join->as('subQ');
        $join->on('subQ.id', 'other_table.user_id');

        $expected = 'INNER JOIN (SELECT id, username FROM users WHERE email ILIKE :AND1) AS subQ ON subQ.id = other_table.user_id';
        $actual = $join->compile();

        $this->assertEquals($expected, $actual);
        $this->assertEquals([':AND1' => '%@yahoo.com'], $subQuery->getParams());
    }
}