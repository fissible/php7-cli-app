<?php declare(strict_types=1);

use PhpCli\Git\Chunk;
use PHPUnit\Framework\TestCase;

class ChunkTest extends TestCase {

    public function testParseRender()
    {
        // git log -p 7ba34821fb3b31b67333184ee0ac4ae5c09f8089^..7ba34821fb3b31b67333184ee0ac4ae5c09f8089

        $diff = '@@ -0,0 +1,8 @@
+<?xml version="1.0" encoding="UTF-8"?>
+<phpunit colors="true" bootstrap="vendor/autoload.php">
+    <testsuites>
+        <testsuite name="all">
+            <directory suffix="Test.php">tests/</directory>
+        </testsuite>
+    </testsuites>
+</phpunit>';

        $Chunk = new Chunk($diff);

        $this->assertEquals($diff, $Chunk.'');
    }
}
