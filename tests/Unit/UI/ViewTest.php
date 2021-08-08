<?php declare(strict_types=1);

namespace Tests\Unit\UI;

use PhpCli\UI\View;
use PhpCli\UI\ViewTemplate;
use Tests\TestCase;

final class ViewTest extends TestCase
{
    public function testRender()
    {
        $viewsPath = __DIR__ . DIRECTORY_SEPARATOR . 'views';
        $data = [
            '#header' => 'Test Header',
            '#session' => 'Username',
            '#container' => 'Line 1'
        ];
        $View = new View('index', $data, [
            new ViewTemplate($viewsPath . '/index.txt'),
            new ViewTemplate($viewsPath . '/index-large.txt')
        ]);
        
        $Grid = $View->render(20, 105);

        $this->assertInstanceOf('PhpCli\Grid', $Grid);
        $this->assertEquals([1, 2], $Grid->find($data['#header']));
        $this->assertEquals([1, 85], $Grid->find($data['#session']));
        $this->assertEquals([3, 2], $Grid->find($data['#container']));

        $Grid = $View->render(30, 105);

        $this->assertInstanceOf('PhpCli\Grid', $Grid);
        $this->assertEquals([1, 2], $Grid->find($data['#header']));
        $this->assertEquals([1, 91], $Grid->find($data['#session']));
        $this->assertEquals([4, 2], $Grid->find($data['#container']));

    }
}