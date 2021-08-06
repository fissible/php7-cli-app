<?php declare(strict_types=1);

namespace PhpCli\Commands;

use PhpCli\Application;
use PhpCli\Command;

class ScreenCommand extends Command
{
    protected Application $Application;

    private array $parameters;

    public function __construct(Application $Application, array $parameters = [])
    {
        parent::__construct($Application);

        $this->parameters = $parameters;
    }

    public function run(): ?Command
    {
        return null;
    }

    public function __invoke()
    {
        return $this->run();
    }
}