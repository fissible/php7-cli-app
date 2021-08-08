<?php declare(strict_types=1);

namespace Tests\Unit\UI;

use PhpCli\ScreenApplication;
use PhpCli\UI\Screen;
use PhpCli\UI\View;
use Tests\TestCase;

final class ScreenTest extends TestCase
{
    public function testMakeView()
    {
        $App = new ScreenApplication();
        $App->setViewsPath(__DIR__ . DIRECTORY_SEPARATOR . 'views');
        $Screen = new Screen($App);
        $templateNames = ['index', 'index-large'];
        $data = [
            '#header' => 'Test Header',
            '#session' => 'Username',
            '#container' => 'Line 1' . "\n" . 'Line 2'
        ];
        $config = null;
        $View = $Screen->makeView('index', $templateNames, $data, $config);

        $this->assertInstanceOf('PhpCli\UI\View', $View);

        $height = 25;
        $width = 100;
        $Grid = $View->render($height, $width);

        // print_r($Grid);

        $this->assertInstanceOf('PhpCli\Grid', $Grid);
    }
}