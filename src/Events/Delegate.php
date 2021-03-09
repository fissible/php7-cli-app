<?php declare(strict_types=1);

namespace PhpCli\Events;

class Delegate
{
    private $subject;

    public function __construct($subject)
    {
        $this->subject = $subject;
    }

    public function Subject()
    {
        return $this->subject;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return call_user_func_array(array($this->subject, $name), $arguments);
    }
}