<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

final class OptionsTest extends TestCase
{
    public $file;

    public function setUp(): void
    {
        parent::setUp();

        $this->file = __DIR__ . 'cli_' . basename(__FILE__);

        $contents = <<<'EOF'
<?php
require('./vendor/autoload.php');
use PhpCli\Options;
$Options = new Options(['v'], ['file'], ['output']);
foreach($Options->get() as $key => $value) {
    print $key . '=>'. (is_bool($value) ? ($value ? 'true' : 'false') : $value)."\n";
}
EOF;

        file_put_contents($this->file, $contents);
    }

    public function testGetAll()
    {
        $this->assertFileExists($this->file);

        exec("php $this->file --file=in.txt --output=out.xml -v", $output, $return);

        $this->assertCount(3, $output);
        $this->assertEquals('file=>in.txt', $output[0]);
        $this->assertEquals('output=>out.xml', $output[1]);
        $this->assertEquals('v=>true', $output[2]);
    }

    public function testGetSome()
    {
        $this->assertFileExists($this->file);

        exec("php $this->file --file=in.txt -v", $output, $return);

        $this->assertCount(2, $output);
        $this->assertEquals('file=>in.txt', $output[0]);
        $this->assertEquals('v=>true', $output[1]);
    }

    public function testGetOne()
    {
        $this->assertFileExists($this->file);

        exec("php $this->file --file=in.txt", $output, $return);

        $this->assertCount(2, $output);
        $this->assertEquals('file=>in.txt', $output[0]);
        $this->assertEquals('v=>false', $output[1]);
    }

    // public function testMissingRequired()
    // {
    //     $this->assertFileExists($this->file);

    //     exec("php $this->file -v 2>/dev/null", $output, $return);
        
    //     $this->assertEquals(255, $return); // UnderflowException
    // }

    public function tearDown(): void
    {
        parent::tearDown();

        unlink($this->file);
    }
}