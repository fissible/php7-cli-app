<?php

require('./vendor/autoload.php');

use PhpCli\Options;

$Options = new Options(['v'], ['file'], ['output']);

$out = $Options->get();
var_dump($out);

print "v: " . ($Options->get('v', false) ? 'VERBOSE' : 'SUCCINCT') . "\n";
print "file: " . $Options->get('file') . "\n";
if ($output = $Options->get('output', false)) {
    print "output: " . $output . "\n";
}