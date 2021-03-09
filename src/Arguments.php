<?php declare(strict_types=1);

namespace PhpCli;

use PhpCli\Collection;
use PhpCli\Events\AddParameterEvent;
use PhpCli\Events\DropParameterEvent;
use PhpCli\Observers\Observable;
use PhpCli\Observers\ArgumentsObserver;
use PhpCli\Observers\Subject;

class Arguments extends Collection implements Subject
{
    use Observable {
        Observable::__construct as private intialize;
    }

    private Parameters $Parameters;

    public function __construct(Parameters $Parameters, $set = null)
    {
        parent::__construct($set);
        $this->Parameters = $Parameters;
        $this->intialize();
        $this->attach(new ArgumentsObserver($this));
    }

    public function Parameters(): Parameters
    {
        return $this->Parameters;
    }

    /**
     * Pull Arguments from the Collection.
     * 
     * @param $filter
     * @return Collection
     */
    public function pull($filter): Collection
    {
        $PulledCollection = parent::pull($filter);
        if ($Parameter = $PulledCollection->first()) {
            $this->notify();
        }

        return $PulledCollection;
    }

    /**
     * Add an Argument to the Collection.
     * 
     * @param $item
     * @return int
     */
    public function push($item): int
    {
        if (!($item instanceof Argument)) {
            throw new \InvalidArgumentException();
        }
        $count = parent::push($item);
        $this->notify();

        return $count;
    }
}