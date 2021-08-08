<?php declare(strict_types=1);

namespace PhpCli\Observers;

use PhpCli\Collection;
use PhpCli\Facades\Log;

trait Observable {

    protected Collection $Observers;

    public function __construct()
    {
        $this->Observers = new Collection();
    }

    public function attach(\SplObserver $Observer): void
    {
        $this->Observers->push($Observer);
    }

    public function detach(\SplObserver $Observer): void
    {
        $this->Observers->pull(function (\SplObserver $Member) use ($Observer) {
            return $Observer === $Member;
        });
    }

    public function notify(): void
    {
        $this->Observers->each(function (\SplObserver $Observer) {
            $Observer->update($this);
        });
    }
}