<?php declare(strict_types=1);

use PhpCli\Git\git;
use PhpCli\Git\File;
use PhpCli\Git\Repository;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase {

    public function testStatuses()
    {
        $filename = 'text.txt';
        $path = sys_get_temp_dir().date('YmdHis');
        mkdir($path);
        $Repo = new Repository($path);

        try {
            $Repo->delete();
        } catch (\Exception $e) {
            // 
        }

        $Repo->init();


        // Test add new file
        file_put_contents($Repo->path($filename), 'Initial content.');

        $File = $Repo->getStatus($filename)[0];

        $this->assertEquals('untracked', $File->getIndexStatus());
        $this->assertEquals('untracked', $File->getWorktreeStatus());

        git::add($filename);

        $File->add();

        $this->assertEquals('added', $File->getIndexStatus());
        $this->assertEquals('unmodified', $File->getWorktreeStatus());

        $File = $Repo->getStatus($filename)[0];

        $this->assertEquals('added', $File->getIndexStatus());
        $this->assertEquals('unmodified', $File->getWorktreeStatus());

        git::commit('-m', '"Initial commit"');

        $Files = $Repo->getStatus($filename);

        $this->assertEmpty($Files);


        // Test rename tracked file
        $newfilename = 'tmp_text.txt';
        rename($Repo->path($filename), $Repo->path($newfilename));

        $File = $Repo->getStatus($filename)[0];

        $this->assertEquals('unmodified', $File->getIndexStatus());
        $this->assertEquals('deleted', $File->getWorktreeStatus());

        git::add($filename);
        git::add($newfilename);

        $File->add(); // This and the consecutive status checks work on imperfect information (cannot detect rename)

        $this->assertEquals('deleted', $File->getIndexStatus());
        $this->assertEquals('unmodified', $File->getWorktreeStatus());

        $Files = $Repo->getStatus(); // Global status check combines add and delete into a rename
        $File = $Files[Repository::STR_CHANGES_TO_BE_COMMITTED][0];

        $this->assertEquals('renamed', $File->getIndexStatus());
        $this->assertEquals('unmodified', $File->getWorktreeStatus());

        rename($Repo->path($newfilename), $Repo->path($filename));
        git::reset('HEAD', $filename);
        git::reset('HEAD', $newfilename);


        // Test copy tracked file
        copy($Repo->path($filename), $Repo->path($newfilename));

        $File = $Repo->getStatus($newfilename)[0];

        $this->assertEquals('untracked', $File->getIndexStatus());
        $this->assertEquals('untracked', $File->getWorktreeStatus());

        unlink($Repo->path($newfilename));


        // Test modify tracked file
        file_put_contents($Repo->path($filename), 'Updated content.');

        $File = $Repo->getStatus($filename)[0];

        $this->assertEquals('unmodified', $File->getIndexStatus());
        $this->assertEquals('modified', $File->getWorktreeStatus());


        // Test deleting tracked file
        unlink($Repo->path('text.txt'));

        $File = $Repo->getStatus($filename)[0];

        $this->assertEquals('unmodified', $File->getIndexStatus());
        $this->assertEquals('deleted', $File->getWorktreeStatus());
        $this->assertTrue($Repo->delete());

        rmdir($path);
    }


}