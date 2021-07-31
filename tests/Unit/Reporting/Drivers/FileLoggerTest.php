<?php declare(strict_types=1);

namespace Tests\Unit;

use PhpCli\Filesystem\Directory;
use PhpCli\Filesystem\File;
use PhpCli\Reporting\Logger;
use PhpCli\Reporting\Drivers\FileLogger;
use PHPUnit\Framework\TestCase;

class FileLoggerTest extends TestCase
{
    public function testLog()
    {
        $path = __DIR__.DIRECTORY_SEPARATOR.'logs';
        $LogDir = new Directory($path);
        $Logger = new FileLogger([
            'path' => $path
        ]);
        $expected = date('Y-m-d H:i:s').' - info: Here is a value'."\n";
        $Logger->log('Here is a value', Logger::INFO);
        $out = $LogDir->files()[0]->read();

        foreach ($LogDir->files() as $File) {
            $File->delete();
        }
        $LogDir->delete();

        $this->assertEquals($expected, $out);
    }
}