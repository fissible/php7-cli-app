<?php declare(strict_types=1);

namespace Tests\Feature;

use PhpCli\Observers\Observer;
use PhpCli\Observers\Observable;
use PhpCli\Observers\Subject;
use PHPUnit\Framework\TestCase;

class ObserverTest extends TestCase
{
    public function testUpdate()
    {
        $Subject = new class implements Subject {

            use Observable;
            
            private $message = '';

            public function get(): string
            {
                return $this->message;
            }

            public function setMessage(string $string): void
            {
                $this->message = $string;
                $this->notify();
            }
        };

        $Observer = new class extends Observer {
            private $message = '';

            public function get(): string
            {
                return $this->message;
            }

            public function set(string $message): void
            {
                $this->message = $message;
            }

            public function update(\SplSubject $Subject): void
            {
                $this->set($Subject->get());
            }
        };

        $expected = md5(date('YmdHis'));
        $Subject->attach($Observer);
        $Subject->setMessage($expected);
        $actual = $Observer->get();

        $this->assertEquals($expected, $actual);
    }
}