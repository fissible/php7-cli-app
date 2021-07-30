<?php declare(strict_types=1);

use PhpCli\Git\Diff;
use PHPUnit\Framework\TestCase;

class DiffTest extends TestCase {

    public function testParse()
    {
        $diff_header = "diff --git a/portal/resources/assets/sass/app.scss b/portal/resources/assets/sass/app.scss
index 3b9831a448..8d1ab9acb9 100755
--- a/portal/resources/assets/sass/app.scss
+++ b/portal/resources/assets/sass/app.scss";
        $chunk1 = "@@ -343,13 +343,13 @@ ol.step-list > li:before {
     margin-left: -75px;
 }

-#roster-tabs.nav-tabs {
+.nav-tabs {
      border-bottom: 4px solid \$csatf-blue;
 }
-#roster-tabs.nav-tabs > li {
+.nav-tabs > li {
    margin-bottom: 0px;
 }
-#roster-tabs.nav-tabs.nav > li > a {
+.nav-tabs.nav > li > a {
    background-color: #f5f5f5;
    border-radius: 10px 10px 0 0;
    color: #666;";
        $chunk2 = "@@ -361,13 +361,13 @@ ol.step-list > li:before {
         color: #fff;
     }
 }
-#roster-tabs.nav-tabs.nav > li > a:hover {
+.nav-tabs.nav > li > a:hover {
     background-color: #cddce9;
     color: \$brand-primary;
 }
-#roster-tabs.nav-tabs > li.active > a,
-#roster-tabs.nav-tabs > li.active > a:hover,
-#roster-tabs.nav-tabs > li.active > a:focus {
+.nav-tabs > li.active > a,
+.nav-tabs > li.active > a:hover,
+.nav-tabs > li.active > a:focus {
     color: #fff;
     cursor: default;
     background-color: \$csatf-blue;";
        $diff = $diff_header."\n".$chunk1."\n".$chunk2;
        $Diff = Diff::parse($diff);
        $chunks = $Diff->chunks();
        
        $this->assertEquals($chunk1, $chunks[0].'');
        $this->assertEquals($chunk2, $chunks[1].'');
    }
}
