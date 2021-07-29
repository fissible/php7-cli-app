<?php declare(strict_types=1);

namespace Tests\Unit\Database\Drivers;

use PhpCli\Database\Drivers\Driver;
use Tests\TestCase;

final class DriverTest extends TestCase
{
    use \Tests\UsesDatabase;

    public function testCreate()
    {
        // $this->expectException(\LogicException::class);
        // $this->expectExceptionMessage('JOIN missing required criteria.');

        $PDO = Driver::create((object) [
            'driver' => 'sqlite',
            'path' => $this->getDatabaseFile()->getPath()
        ]);

        $this->assertInstanceOf(\PDO::class, $PDO);
    }

    protected function tearDown(): void
    {
        $File = $this->getDatabaseFile();
        if ($File->exists()) {
            $File->delete();
        }
    }
}