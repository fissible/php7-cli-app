<?php declare(strict_types=1);

namespace Tests\Unit;

use PhpCli\Filesystem\File;
use Tests\TestCase;

final class FileTest extends TestCase
{

    public $path;

    public $File;

    public function setUp(): void
    {
        $this->path = __DIR__.'/testFile.txt';
        $this->File = new File($this->path);
    }

    public function testChmodFail()
    {
        $this->expectException(\PhpCli\Exceptions\FileNotFoundException::class);

        $this->File->chmod(0777);
    }

    public function testChmod()
    {
        $this->assertTrue(touch($this->path));
        $this->assertTrue($this->File->chmod(0777));
    }

    public function testExists()
    {
        $this->assertFalse($this->File->exists());

        $UnitDir = new File(__DIR__);

        $this->assertTrue($UnitDir->exists());
    }

    public function testFiles()
    {
        $UnitDir = new File(__DIR__);

        $this->assertTrue($UnitDir->isDir());

        $this->assertNotEmpty($UnitDir->files());
    }

    public function testFilesMatch()
    {
        $UnitDir = new File(__DIR__);

        $OptionTestFiles = $UnitDir->filesMatch(function ($File) {
            return preg_match('/\AOption/', $File->getFilename()) === 1;
        });

        $fileNames = array_map(function ($File) {
            return $File->getFilename();
        }, $OptionTestFiles);

        $this->assertContains('OptionTest.php', $fileNames);
        $this->assertContains('OptionsTest.php', $fileNames);
        $this->assertNotContains('FileTest.php', $fileNames);
    }

    public function testLines()
    {
        $fileLines = [
            'This is a line.',
            'And this is another line.',
            'Times are not equal.',
            'This is not a directory.',
            'Sometimes the file is not found.',
            'And other time another line is not found.',
            'This is finally the last line.'
        ];
        file_put_contents($this->path, implode("\n", $fileLines));

        foreach ($this->File->lines() as $key => $line) {
            $this->assertEquals($fileLines[$key], $line);
        }
    }

    public function testRead()
    {
        $fileLines = [
            'Sometimes the file is not found.',
            'And other time another line is not found.',
            'This is finally the last line.'
        ];
        $expected = implode("\n", $fileLines);
        file_put_contents($this->path, $expected);
        $actual = $this->File->read();

        $this->assertEquals($expected, $actual);
    }

    public function testWrite()
    {
        $fileLines = [
            'Sometimes the file is not found.',
            'And other time another line is not found.',
            'This is finally the last line.'
        ];
        $expected = implode("\n", $fileLines);
        $this->File->write($expected);
        $actual = file_get_contents($this->path);

        $this->assertEquals($expected, $actual);
    }

    public function tearDown(): void
    {
        if ($this->File->exists()) {
            $this->File->delete();
        }
    }
}