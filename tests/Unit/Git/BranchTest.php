<?php declare(strict_types=1);

use PhpCli\Git\Branch;
use PHPUnit\Framework\TestCase;

class BranchTest extends TestCase {

    public function testName()
    {
        $Branch = new Branch('development');

        $this->assertEquals('development', $Branch->name());
    }
}