<?php declare(strict_types=1);

namespace Tests\Unit;

use PhpCli\Exceptions\FileNotFoundException;
use PhpCli\Exceptions\InvalidFileModeException;
use PhpCli\Filesystem\File;
use Tests\TestCase;

final class FileTest extends TestCase
{
    public $dirpath;

    public $path;

    public $File;

    public function setUp(): void
    {
        $this->dirpath = __DIR__ . DIRECTORY_SEPARATOR . uniqid();
        $this->path = $this->dirpath . '/testFile.txt';
        $this->File = new File($this->path);
    }

    public function testTemp()
    {
        $content = uniqid();
        $File = File::temp();
        mkdir($this->dirpath);
        $File->write($content);
        $path = $File->path;

        $this->assertTrue($File->exists());
        $this->assertTrue(file_exists($path));
        $this->assertEquals($content, $File->read());

        unset($File);

        $this->assertFalse(file_exists($path));
    }

    public function testChmodFail()
    {
        $this->expectException(\PhpCli\Exceptions\FileNotFoundException::class);

        $this->File->chmod(0777);
    }

    public function testChmod()
    {
        mkdir($this->dirpath);
        $this->assertTrue(touch($this->path));
        $this->assertNotEquals(0777, $this->File->getPermissions());
        $this->assertTrue($this->File->chmod(0777));
        $this->assertEquals('777', $this->File->getPermissionsString());
    }

    public function testCopy()
    {
        /**
         * Set the file mode for reading/writing operations.
         * 
         *  Mode   Creates  Reads   Writes  Pointer-Starts  Truncates-File  Notes                           Purpose
         *  r               y               beginning                       fails if file doesn't exist     basic read existing file
         *  r+              y       y       beginning                       fails if file doesn't exist     basic r/w existing file
         *  w       y               y       beginning+end   y                                               create, erase, write file
         *  w+      y       y       y       beginning+end   y                                               create, erase, write file with read option
         *  a       y               y       end                                                             write from end of file, create if needed
         *  a+      y       y       y       end                                                             write from end of file, create if needed, with read options
         *  x       y               y       beginning                       fails if file exists            like w, but prevents over-writing an existing file
         *  x+      y       y       y       beginning                       fails if file exists            like w+, but prevents over writing an existing file
         *  c       y               y       beginning                                                       open/create a file for writing without deleting current content
         *  c+      y       y       y       beginning                                                       open/create a file that is read, and then written back down
         */


        mkdir($this->dirpath);
        $srcPath = $this->dirpath.DIRECTORY_SEPARATOR.'src.json';
        $copyPath = $this->dirpath.DIRECTORY_SEPARATOR.'copy.json';
        file_put_contents($srcPath, "{\n\t\"ttl\": 86400\n}");

        $Config = new File($srcPath, File::EXISTS_READ_ONLY); // r  - read only, existing file
        $ConfigCopy = new File($copyPath, File::CREATE_READ_WRITE); // x+  - overwrite file (+ read option), prevents over-writing an existing file

        $this->assertTrue(file_exists($Config->path));
        $this->assertFalse(file_exists($ConfigCopy->path));

        $Config->copy($ConfigCopy);

        $this->assertEquals(file_get_contents($srcPath), file_get_contents($copyPath));

        $Config->setMode(File::CREATE_TRUNCATE_READ_WRITE);
        $Config->write("{\n\t\"ttl\": 300\n}");

        $this->assertNotEquals(file_get_contents($srcPath), file_get_contents($copyPath));

        $ConfigCopy->setMode(File::CREATE_TRUNCATE_READ_WRITE); // w+  - overwrite file (+ read option)
        $Config->copy($ConfigCopy);

        $this->assertEquals(file_get_contents($srcPath), file_get_contents($copyPath));
    }

    public function testCopyFileToDirectory()
    {
        mkdir($this->dirpath);
        $dirPath = $this->dirpath . DIRECTORY_SEPARATOR . uniqid();
        $srcPath = $this->dirpath . DIRECTORY_SEPARATOR . date('Ymd') . '.json';
        $copyPath = $dirPath . DIRECTORY_SEPARATOR . date('Ymd') . '.json';
        file_put_contents($srcPath, "{\n\t\"hits\": 147\n}");

        $DayLog = new File($srcPath);
        $ArchiveDirectory = new File($dirPath);

        $this->assertTrue(file_exists($srcPath));
        $this->assertTrue(file_exists($DayLog->path));
        $this->assertFalse(file_exists($ArchiveDirectory->path));

        $DayLog->copy($ArchiveDirectory);

        $this->assertTrue(file_exists($ArchiveDirectory->path));
        $this->assertTrue(file_exists($copyPath));
        $this->assertEquals(file_get_contents($srcPath), file_get_contents($copyPath));
    }

    public function testCopyDirectoryToDirectory()
    {
        $filename = date('Ymd') . '.txt';
        $srcPath = $this->dirpath . DIRECTORY_SEPARATOR . uniqid('a');
        $srcFilePath = $srcPath . DIRECTORY_SEPARATOR . $filename;
        $destPath = $this->dirpath . DIRECTORY_SEPARATOR . uniqid('b');
        $destFilePath = $destPath . DIRECTORY_SEPARATOR . $filename;
        mkdir($srcPath, 0777, true);
        file_put_contents($srcFilePath, 'This is just the file.');

        $SrcDir = new File($srcPath);
        $DestDir = new File($destPath);

        $this->assertTrue(file_exists($srcPath));
        $this->assertTrue(file_exists($srcFilePath));
        $this->assertFalse(file_exists($destPath));

        $SrcDir->copy($DestDir);

        $this->assertTrue(file_exists($destPath));
        $this->assertTrue(file_exists($destFilePath));
        $this->assertEquals(file_get_contents($srcFilePath), file_get_contents($destFilePath));
    }

    public function testCreateFailsFileMode()
    {
        mkdir($this->dirpath);
        file_put_contents($this->path, 'test');
        $File = new File($this->path, File::EXISTS_READ_ONLY);

        $this->assertEquals('r', $File->getMode());
        $this->expectException(InvalidFileModeException::class);
        $this->expectExceptionMessage(sprintf('Error creating file: %s has an invalid file mode %s', $this->path, File::EXISTS_READ_ONLY));

        $File->create();
    }

    public function testCreateFailsFileExists()
    {
        mkdir($this->dirpath);
        file_put_contents($this->path, 'test');
        $File = new File($this->path);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(sprintf('File at path "%s" already exists.', $this->path));

        $File->create();
    }

    public function testCreate()
    {
        $path = __DIR__ . DIRECTORY_SEPARATOR . uniqid() . '.txt';
        $File = new File($path);
        $path = $File->path;

        $this->assertFalse(file_exists($path));

        $File->create();

        $this->assertTrue(file_exists($path));

        unlink($path);

        $this->assertFalse(file_exists($path));

        $DirFile = new File($this->dirpath);

        $this->assertFalse(file_exists($path));

        $DirFile->create();

        $this->assertTrue(file_exists($this->dirpath));
    }

    public function testDeleteFailsFileMode()
    {
        mkdir($this->dirpath);

        $this->assertTrue(is_dir($this->dirpath));

        $DirFile = new File($this->dirpath, File::EXISTS_READ_ONLY);

        $this->expectException(InvalidFileModeException::class);
        $this->expectExceptionMessage(sprintf('Error deleting file: %s has an invalid file mode %s', $this->dirpath, File::EXISTS_READ_ONLY));

        $DirFile->delete();
    }

    public function testDeleteFailsDirectoryNotEmpty()
    {
        $DirFile = new File($this->dirpath);
        mkdir($this->dirpath);
        file_put_contents($this->path, 'test');

        $this->assertTrue(file_exists($this->path));
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(sprintf('Directory "%s" not empty.', $this->dirpath));

        $DirFile->delete();
    }

    public function testDelete()
    {
        $DirFile = new File($this->dirpath);

        $this->assertFalse(file_exists($this->dirpath));

        mkdir($this->dirpath);

        $this->assertTrue(file_exists($this->dirpath));

        $path = $this->dirpath . DIRECTORY_SEPARATOR . uniqid() . '.txt';
        $File = new File($path);

        $this->assertFalse(file_exists($path));

        file_put_contents($path, 'test');

        $this->assertTrue(file_exists($path));

        $File->delete();

        $this->assertFalse(file_exists($path));

        $DirFile->delete();

        $this->assertFalse(file_exists($this->dirpath));
    }

    public function testEmptyNotFound()
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage(sprintf('File "%s" not found', $this->path));

        $this->File->empty();
    }

    public function testEmpty()
    {
        $DirFile = new File($this->dirpath);
        mkdir($this->dirpath);

        $this->assertTrue($DirFile->empty());

        touch($this->path);

        $this->assertTrue($this->File->empty());

        file_put_contents($this->path, 'data');

        $this->assertFalse($DirFile->empty());
        $this->assertFalse($this->File->empty());
    }

    public function testExists()
    {
        $path = __DIR__ . DIRECTORY_SEPARATOR . uniqid() . '.txt';
        $File = new File($path);

        $this->assertFalse($this->File->exists());
        $this->assertFalse(file_exists($path));

        file_put_contents($path, 'test');

        $this->assertTrue(file_exists($path));
        $this->assertTrue($File->exists());

        unlink($path);

        $this->assertFalse(file_exists($path));
        $this->assertFalse($File->exists());
    }

    public function testFilesDirectoryNotFound()
    {
        $Dir = new File($this->dirpath);

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage(sprintf('File "%s" not found', $this->dirpath));

        $Dir->files();
    }

    public function testFilesInvalidArgumentException()
    {
        $File = new File($this->path);
        mkdir($this->dirpath);
        file_put_contents($this->path, 'test');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('This file is not a directory.');

        $File->files();
    }

    public function testFiles()
    {
        $UnitDir = new File(__DIR__);

        $this->assertTrue($UnitDir->isDir());

        $files = $UnitDir->files();

        $this->assertNotEmpty($files);
        $this->assertInstanceOf(File::class, $files[0]);
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

    public function testGetDir()
    {
        mkdir($this->dirpath);
        file_put_contents($this->path, 'test');

        $Dir = $this->File->getDir();

        $this->assertEquals($this->dirpath, $Dir->getPath());
    }

    public function testGetExtension()
    {
        mkdir($this->dirpath);
        file_put_contents($this->path, 'test');

        $extension = $this->File->getExtension();

        $this->assertEquals('txt', $extension);
    }

    public function testGetFilename()
    {
        $expected = basename($this->path);
        $actual = $this->File->getFilename();

        $this->assertEquals($expected, $actual);

        mkdir($this->dirpath);
        file_put_contents($this->path, 'test');

        $expected = basename($this->path);
        $actual = $this->File->getFilename();

        $this->assertEquals($expected, $actual);
    }

    public function testGetMimeNotFound()
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage(sprintf('File "%s" not found', $this->path));

        $this->File->getMime();
    }

    public function testGetMime()
    {
        mkdir($this->dirpath);
        file_put_contents($this->path, 'test');

        $expected = mime_content_type($this->path);
        $actual = $this->File->getMime();

        $this->assertEquals($expected, $actual);
    }

    public function testGetMode()
    {
        $mode = $this->File->getMode();

        $this->assertEquals('a+', $mode);
        $this->assertEquals(FILE::READ_WRITE_APPEND, $mode);

        $File = new File($this->path, FILE::WRITE_ONLY_APPEND);
        $mode = $File->getMode();

        $this->assertEquals('a', $mode);
        $this->assertEquals(FILE::WRITE_ONLY_APPEND, $mode);
    }

    public function testGetPath()
    {
        $this->assertEquals($this->path, $this->File->getPath());
    }

    public function testGetParent()
    {
        mkdir($this->dirpath);
        file_put_contents($this->path, 'test');

        $Parent = $this->File->getParent();

        $this->assertEquals($this->dirpath, $Parent->getPath());
    }

    public function testGetPermissionsNotFound()
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage(sprintf('File "%s" not found', $this->path));

        $this->assertEquals('0644', $this->File->getPermissions());
    }

    public function testGetPermissions()
    {
        mkdir($this->dirpath);
        file_put_contents($this->path, 'test');
        $expected = fileperms($this->path);

        $this->assertEquals($expected, $this->File->getPermissions());
    }

    public function testGetPermissionsString()
    {
        mkdir($this->dirpath);
        file_put_contents($this->path, 'test');
        $expected = decoct(fileperms($this->path) & 0777);

        $this->assertEquals($expected, $this->File->getPermissionsString());
    }

    public function testGetInfo()
    {
        mkdir($this->dirpath);
        $info = pathinfo($this->dirpath);
        $Dir = new File($this->dirpath);
 
        $this->assertEquals($info, $Dir->info());
    }

    public function testIsDir()
    {
        $Dir = new File($this->dirpath);

        $this->assertTrue($Dir->isDir());

        mkdir($this->dirpath);

        $this->assertTrue($Dir->isDir());
        $this->assertFalse($this->File->isDir());

        file_put_contents($this->path, 'test');

        $this->assertFalse($this->File->isDir());
    }

    public function testLinesNotFound()
    {
        $this->assertFalse($this->File->exists());
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage(sprintf('File "%s" not found', $this->path));

        foreach ($this->File->lines() as $_);
    }

    public function testLinesFailsIsDirectory()
    {
        $Dir = new File($this->dirpath);
        mkdir($this->dirpath);

        $this->assertTrue($Dir->isDir());
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('This file is a directory.');

        foreach ($Dir->lines() as $_);
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
        mkdir($this->dirpath);
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
        mkdir($this->dirpath);
        file_put_contents($this->path, $expected);
        $actual = $this->File->read();

        $this->assertEquals($expected, $actual);
    }

    public function testRename()
    {
        $newName = 'renamedFile.txt';

        $this->assertNotEquals($newName, basename($this->File->getPath()));

        $renamed = $this->File->rename($newName);
        $path = $this->File->getPath();

        $this->assertTrue($renamed);
        $this->assertEquals($newName, basename($this->File->getPath()));
        $this->assertNotEquals($this->path, $path);

        mkdir($this->dirpath);
        file_put_contents($path, 'Test file contents');

        $newName = 'renamedFileAgain.txt';
        $expected = file_get_contents($path);

        $renamed = $this->File->rename($newName);
        $path = $this->File->getPath();

        $this->assertTrue($renamed);
        $this->assertEquals($newName, basename($this->File->getPath()));
        $this->assertEquals($expected, file_get_contents($path));
    }

    public function testWrite()
    {
        $fileLines = [
            'Sometimes the file is not found.',
            'And other time another line is not found.',
            'This is finally the last line.'
        ];
        $expected = implode("\n", $fileLines);
        mkdir($this->dirpath);
        $this->File->write($expected);
        $actual = file_get_contents($this->path);

        $this->assertEquals($expected, $actual);
    }

    public function tearDown(): void
    {
        if (file_exists($this->dirpath)) {
            $Dir = new File($this->dirpath);
            $Dir->delete(true);
        }

        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }
}