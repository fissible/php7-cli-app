<?php declare(strict_types=1);

use PhpCli\Git\Author;
use PhpCli\Git\Commit;
use PHPUnit\Framework\TestCase;

class CommitTest extends TestCase {

    public function testGetAuthor()
    {
        $Author = Author::parse('Allen McCabe <allenmccabe@gmail.com>');
        $Commit = new Commit('96385546eff52e5f7f19f26d66109036617b2163');

        $this->assertEquals($Author, $Commit->getAuthor());
    }

    public function testGetDate()
    {
        $date = \DateTime::createFromFormat('Y-m-d H:i:s T', '2021-07-29 18:51:55 -0700');
        $Commit = new Commit('acb258581565365f69f11a820248c64cc099bfbb');

        $this->assertEquals($date, $Commit->getDate());
    }

    public function testGetHash()
    {
        $hash = '0fdad22ce7bb14f499dd7a7ea43a7b053d72b777';
        $Commit = new Commit($hash);

        $this->assertEquals($hash, $Commit->getHash());
    }
}