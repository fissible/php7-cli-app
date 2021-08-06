<?php declare(strict_types=1);

namespace PhpCli\Observers;

use PhpCli\UI\Screen;
use PhpCli\Facades\Log;
use PhpCli\Git\Repository;
use PhpCli\Reporting\Drivers\BufferLogger;

class LogObserver extends Observer
{
    private Screen $Screen;

    private string $componentName;

    public function __construct(Screen $Screen, string $componentName)
    {
        parent::__construct();

        $this->Screen = $Screen;
        $this->componentName = $componentName;
    }

    public function update(\SplSubject $Subject): void
    {
        if ($Subject instanceof Repository && $Logger = $Subject->Logger()) {
            if ($Logger instanceof BufferLogger && $Component = $this->Screen->getComponent($this->componentName)) {
                if ($content = $Logger->Buffer->flush()) {
                    $Component->setContent(implode("\n", $content));
                    $this->Screen->draw();
                }
            }
        }
    }
}
