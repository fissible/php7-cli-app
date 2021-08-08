<?php declare(strict_types=1);

use PhpCli\Git\git;
use PhpCli\Git\Repository;
use PHPUnit\Framework\TestCase;

class RepositoryTest extends TestCase {

    public function testDelete()
    {
        $Repo = new Repository(dirname(__FILE__));

        $this->assertFalse($Repo->isInitialized());
        $this->assertTrue($Repo->init());
        $this->assertTrue($Repo->isInitialized());
        $this->assertTrue($Repo->delete());
        $this->assertFalse($Repo->isInitialized());
    }

    public function testGetBranch()
    {
        $repo_path = dirname(__FILE__) . '/mergeRepo';
        $Repo = new Repository($repo_path);

        if (file_exists($Repo->path('myfile'))) {
            unlink($Repo->path('myfile'));
        }

        if (is_dir($Repo->path('.git'))) {
            $Repo->delete();
        }

        if (is_dir($repo_path)) {
            rmdir($repo_path);
        }

        mkdir($repo_path);
        // chdir($repo_path);

        $this->assertTrue($Repo->init());

        file_put_contents($Repo->path('myfile'), 'This is C');
        $Repo->add('myfile')->commit('C');

        $output = git::symbolic_ref('--short', '-q', 'HEAD');

        $expected = $output[0];
        $actual = $Repo->getBranch()->name();

        $this->assertEquals($expected, $actual);
    }

    public function testGetName()
    {
        $Repo = new Repository(dirname(__FILE__));

        $this->assertTrue($Repo->init());

        $expected = 'Unnamed repository';
        $actual = $Repo->getName();

        $this->assertEquals($expected, $actual);

        $expected = 'New repository';
        file_put_contents($Repo->path('.git/description'), $expected);
        $actual = $Repo->getName();

        $this->assertEquals($expected, $actual);
        $this->assertTrue($Repo->delete());
    }

    public function testGetValidatedCloneParameters()
    {
        $source_repo_path = dirname(__FILE__) . '/cloneRepo';
        $SourceRepo = new Repository($source_repo_path);
        $repo_path = dirname(__FILE__) . '/newRepo';
        $Repo = new Repository($repo_path);

        [$repository, $directory, $destination] = $Repo->getValidatedCloneParameters('.', 'clone_dir');

        $this->assertEquals($Repo->path(), $repository);
        $this->assertEquals('clone_dir', $directory);
        $this->assertEquals($Repo->path('clone_dir'), $destination);

        [$repository, $directory, $destination] = $Repo->getValidatedCloneParameters('./');

        $this->assertEquals($Repo->path(), $repository);
        $this->assertEquals('newRepo_copy', $directory);
        $this->assertEquals($Repo->path('newRepo_copy'), $destination);

        [$repository, $directory, $destination] = $Repo->getValidatedCloneParameters('https://github.com/ajthenewguy/php7-matrix');

        $this->assertEquals('https://github.com/ajthenewguy/php7-matrix', $repository);
        $this->assertEquals(null, $directory);
        $this->assertEquals($Repo->path('php7-matrix'), $destination);

        [$repository, $directory, $destination] = $Repo->getValidatedCloneParameters('https://github.com/ajthenewguy/php7-matrix', 'php_matrix');

        $this->assertEquals('https://github.com/ajthenewguy/php7-matrix', $repository);
        $this->assertEquals('php_matrix', $directory);
        $this->assertEquals($Repo->path('php_matrix'), $destination);
    }

    public function testInit()
    {
        $Repo = new Repository(dirname(__DIR__));

        $this->assertTrue($Repo->init());
    }

    public function testIsInitialized()
    {
        $path = dirname(__DIR__);
        $Repo = new Repository($path);

        $this->assertTrue($Repo->isInitialized());

        $path = dirname(__FILE__);
        $Repo = new Repository($path);

        $this->assertFalse($Repo->isInitialized());
    }

    public function testMerge()
    {
        $repo_path = dirname(__FILE__).'/mergeRepo';
        $Repo = new Repository($repo_path);

        if (file_exists($Repo->path('myfile'))) {
            unlink($Repo->path('myfile'));
        }
        
        if (is_dir($Repo->path('.git'))) {
            $Repo->delete();
        }

        if (is_dir($repo_path)) {
            rmdir($repo_path);
        }
        
        mkdir($repo_path);

        $this->assertTrue($Repo->init());

        file_put_contents($Repo->path('myfile'), 'This is C');
        $Repo->add('myfile')->commit('C');

        $Repo->checkout('A', null, true);
        file_put_contents($Repo->path('myfile'), 'This is A1');
        $Repo->commit('A1', 'myfile');

        $Repo->checkout('B', null, true);
        file_put_contents($Repo->path('myfile'), 'This is B1');
        $Repo->commit('B1', 'myfile');

        $Repo->checkout('master');
        $Repo->merge('B', 'A');
        $Repo->merge('A', 'B');

        $this->assertTrue($Repo->delete());
        unlink($Repo->path('myfile'));
        rmdir($repo_path);
     }

    public function testSetName()
    {
        $Repo = new Repository(dirname(__FILE__));

        $this->assertTrue($Repo->init());

        $expected = 'New repository';

        $this->assertTrue($Repo->setName($expected));

        $actual = file_get_contents($Repo->path('.git/description'));

        $this->assertEquals($expected, $actual);
        $this->assertTrue($Repo->delete());
    }

    public function testStatus()
    {
        $Repo = new Repository(dirname(__DIR__));
        $status = $Repo->getStatus();

// foreach ($status as $key => $Files) {
//     $status[$key] = array_map(function ($File) {
//         return [
//             'path' => $File->getPath(),
//             'index_status' => $File->getIndexStatus(),
//             'worktree_status' => $File->getWorktreeStatus(),
//             'original_path' => $File->getOriginalPath()
//         ];
//     }, $Files);
// }
// print "\n";
// var_dump($status);
// print "\n";

        $this->assertIsArray($status);
    }

    public function testVersion()
    {
        $Repo = new Repository(dirname(__DIR__));

        $this->assertNotEmpty($Repo->version());
    }

    public function testVersionException()
    {
        $path = dirname(__FILE__);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('A git repository was not found in '.$path);

        $Repo = new Repository($path);
        $Repo->version();
    }
}