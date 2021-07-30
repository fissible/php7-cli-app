<?php declare(strict_types=1);

use PhpCli\Git\Author;
use PHPUnit\Framework\TestCase;

class AuthorTest extends TestCase {

    public function testName()
    {
        $Author = new Author('Bill Fredrickson', 'bfred@rickson.com');

        $this->assertEquals('Bill Fredrickson', $Author->name());
    }

    public function testEmail()
    {
        $Author = new Author('Bill Fredrickson', 'bfred@rickson.com');

        $this->assertEquals('bfred@rickson.com', $Author->email());
    }

    public function testNullEmail()
    {
        $Author = new Author('Bill Fredrickson');

        $this->assertNull($Author->email());
    }

    public function testParse()
    {
        $Author = Author::parse('Bill Fredrickson <bfred@rickson.com>');

        $this->assertEquals('Bill Fredrickson', $Author->name());
        $this->assertEquals('bfred@rickson.com', $Author->email());
        
        $Author = Author::parse('Bill Fredrickson');

        $this->assertEquals('Bill Fredrickson', $Author->name());
        $this->assertNull($Author->email());
    }
}