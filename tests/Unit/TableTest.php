<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use PhpCli\Application;
use PhpCli\Table;

final class TableTest extends TestCase
{
    public function testRender()
    {
        $app = new Application();
        $headers = [
            ' ', 'Name', 'Company', 'Title', 'Email'
        ];
        $rows = [
            [1, 'Tom Jones', 'Universal Studios, Inc.', 'CEO', 'ceo@universalstudios.com'],
            [2, 'Carl Plinker', 'Google Inc.', 'CFO', 'cplinker@google.com'],
            [3, 'Sasha Freeman', 'Best Buy, Inc.', 'Vice President of Operations', 'sfreeman@bestbuy.com'],
            [4, 'Paul Johnson', 'Network Solutions, Inc.', 'Regional Manager', 'pjohnson@networksolutions.com'],
            [5, 'Sandra Cho', 'Zipper Interactive, Inc.', 'Procurement Manager', 'scho@zipper.com']
        ];
        $T = new Table($app, $headers, $rows);

        $expected = '
┌───┬───────────────┬──────────────────────────┬──────────────────────────────┬───────────────────────────────┐
│   │ Name          │ Company                  │ Title                        │ Email                         │
├───┼───────────────┼──────────────────────────┼──────────────────────────────┼───────────────────────────────┤
│ 1 │ Tom Jones     │ Universal Studios, Inc.  │ CEO                          │ ceo@universalstudios.com      │
│ 2 │ Carl Plinker  │ Google Inc.              │ CFO                          │ cplinker@google.com           │
│ 3 │ Sasha Freeman │ Best Buy, Inc.           │ Vice President of Operations │ sfreeman@bestbuy.com          │
│ 4 │ Paul Johnson  │ Network Solutions, Inc.  │ Regional Manager             │ pjohnson@networksolutions.com │
│ 5 │ Sandra Cho    │ Zipper Interactive, Inc. │ Procurement Manager          │ scho@zipper.com               │
└───┴───────────────┴──────────────────────────┴──────────────────────────────┴───────────────────────────────┘
';
        $renderedArray = $T->render();
        $actual = "\n" . implode($renderedArray);

        $this->assertEquals($expected, $actual);
    }
}