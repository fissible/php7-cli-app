<?php declare(strict_types=1);

namespace PhpCli;

class Options {

    private array $required;

    private array $optional;

    private array $boolean;

    private array $parsed;

    private $raw;

    /**
     * @param array $boolean  These options do not accept values
     * @param array $required These options require values
     * @param array $optional These options do not require values
     */
    public function __construct(array $boolean = [], array $required = [], array $optional = [])
    {
        $this->required = [];
        $this->optional = [];
        $this->boolean = [];

        foreach ($required as $req) {
            $this->required[] = trim($req, ':');
        }

        foreach ($optional as $opt) {
            $this->optional[] = trim($opt, ':');
        }

        foreach ($boolean as $bool) {
            $this->boolean[] = trim($bool);
        }
    }

    public function parse(): array
    {
        if (isset($this->parsed)) {
            return $this->parsed;
        }

        $shortopts = '';
        $longopts = [];
        $this->raw = [];

        foreach ($this->required as $req) {
            if (strlen($req) > 1) {
                $name = '--'.$req;
                $longopts[] = $req . ':';
            } else {
                $name = '-' . $req;
                $shortopts .= $req . ':';
            }
            $this->raw[] = [
                'name' => $name,
                'flag' => false,
                'required' => true
            ];
        }

        foreach ($this->optional as $opt) {
            if (strlen($opt) > 1) {
                $name = '--' . $opt;
                $longopts[] = $opt . '::';
            } else {
                $name = '-' . $opt;
                $shortopts .= $opt . '::';
            }
            $this->raw[] = [
                'name' => $name,
                'flag' => false,
                'required' => false
            ];
        }

        foreach ($this->boolean as $bool) {
            if (strlen($bool) > 1) {
                $name = '--' . $bool;
                $longopts[] = $bool;
            } else {
                $name = '-' . $bool;
                $shortopts .= $bool;
            }
            $this->raw[] = [
                'name' => $name,
                'flag' => true,
                'required' => false
            ];
        }

        $options = getopt($shortopts, $longopts);

        // These options do not accept values
        foreach ($this->boolean as $option) {
            $options[$option] = array_key_exists($option, $options);
        }

        // These options require values
        foreach ($this->required as $option) {
            if (isset($options[$option])) {
                switch ($options[$option]) {
                    case 'true':
                    case 'false':
                        $options[$option] = filter_var($options[$option], FILTER_VALIDATE_BOOLEAN);
                    break;
                }
            } else {
                // throw new \UnderflowException(sprintf('Parameter "%s" requires a value ', $option));
            }
        }

        // These options do not require values
        foreach ($this->optional as $option) {
            if (isset($options[$option])) {
                switch ($options[$option]) {
                    case false:
                        $options[$option] = null;
                        break;
                    case 'true':
                    case 'false':
                        $options[$option] = filter_var($options[$option], FILTER_VALIDATE_BOOLEAN);
                        break;
                }
            }
        }

        $this->parsed = $options;
 
        return $this->parsed;
    }

    public function get(string $key = null, $default = null)
    {
        $options = $this->parse();

        if (!is_null($key)) {
            if (!isset($options[$key])) {
                return $default;
            }
            return $options[$key];
        }

        return $options;
    }

    public function getRaw()
    {
        return $this->raw;
    }

    public function __get($name)
    {
        return $this->get(str_replace('_', '-', $name));
    }
}