<?php declare(strict_types=1);

namespace PhpCli\UI;

use PhpCli\Arr;
use PhpCli\Buffer;
use PhpCli\Interfaces\Config;
use PhpCli\Collection;
use PhpCli\Grid;
use PhpCli\Filesystem\File;
use PhpCli\Output;
use PhpCli\Str;
use PhpCli\Traits\HasConfig;

class View
{
    use HasConfig;

    private string $name;

    private Collection $Templates;

    private array $data;

    private array $defaults = [
        'border-style' => 'light',
        'padding' => 1,
        'styles' => []
    ];

    private array $offset = [
        'y' => 0,
        'x' => 0
    ];

    private array $variables = [];

    public function __construct(string $name, array $data = [], $Files = [], $config = null)
    {
        $this->name = $name;
        $this->data = $data;
        $this->Templates = new Collection($Files);

        if ($config instanceof \stdClass) {
            $this->defaults = array_merge($this->defaults, Arr::fromObject($config));
        }

        $this->variables = $this->getVariables();
        $this->Components = $this->getComponents();
    }

    /**
     * Check if the Component has content.
     * 
     * @param string $name
     * @return bool
     */
    public function componentHasContent(string $name, int $height, int $width): bool
    {
        if ($this->hasComponent($name, $height, $width)) {
            return $this->getComponent($name, $height, $width)->hasContent();
        }
        return false;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get the view data.
     * 
     * @return array
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Get the specified Component.
     * 
     * @param string $name
     * @return Component|null
     */
    public function getComponent(string $name, int $height, int $width): ?Component
    {
        $Template = $this->getFile($height, $width);

        return $Template->getComponents()->first(function (Component $Component) use ($name) {
            return $Component->name() === $name;
        });
    }

    /**
     * Check if the specified Component exists.
     * 
     * @param string $name
     * @return bool
     */
    public function hasComponent(string $name, int $height, int $width): bool
    {
        $Template = $this->getFile($height, $width);

        return $Template->getComponents()->contains(function (Component $Component) use ($name) {
            return $Component->name() === $name;
        });
    }

    // /**
    //  * Add a custom Component to the View.
    //  * 
    //  * @param string $name
    //  * @param Component $Component
    //  */
    // public function setComponent(Component $Component): self
    // {
    //     $this->Components->set($Component->name, $Component);

    //     return $this;
    // }

    /**
     * Set the template file.
     * 
     * @param ViewTemplate $Template
     * @return self
     */
    public function pushTemplate(ViewTemplate $Template): self
    {
        $this->Templates->push($Template);

        return $this;
    }

    private function replaceComponents(ViewTemplate $Template)
    {
        $variant = $this->borderVariant();
        $borderChars = [
            'hor' => Output::uchar('hor', $variant),
            'ver' => Output::uchar('ver', $variant),
            'down_right' => Output::uchar('down_right', $variant),
            'down_left' => Output::uchar('down_left', $variant),
            'up_right' => Output::uchar('up_right', $variant),
            'up_left' => Output::uchar('up_left', $variant),
            'ver_right' => Output::uchar('ver_right', $variant),
            'ver_left' => Output::uchar('ver_left', $variant),
            'down_hor' => Output::uchar('down_hor', $variant),
            'up_hor' => Output::uchar('up_hor', $variant),
            'cross' => Output::uchar('cross', $variant)
        ];

        $Template->getComponents()->each(function (Component $Component) use ($Template, $variant, $borderChars) {
            // Erase the Component ID string
            $componentId = '#'.$Component->name;
            if ($coords = $Template->getGrid()->find($componentId)) {

                $chars = Str::split($componentId);
                foreach ($chars as $x => $char) {
                    if ($Template->getGrid()->valid($coords[0], $coords[1] + $x)) {
                        $Template->getGrid()->set($coords[0], $coords[1] + $x, ' ');
                    }
                }
            }

            // Map the content into the view Grid
            $lines = $Component->render();

            foreach ($lines as $y => $line) {
                $chars = Str::split($line);
                
                foreach ($chars as $x => $char) {
                    if (is_null($char)) $char = ' ';
                    $newY = $Component->y + $y;
                    $newX = $Component->x + $x;

                    if ($Template->getGrid()->valid($newY, $newX)) {
                        $currChar = $Template->getGrid()->get($newY, $newX);
                        // check for intersecting borders
                        if (in_array($char, array_values($borderChars))) {
                            if (in_array($currChar, array_values($borderChars))) {
                                $char = Output::combine_lines($currChar, $char, $variant);
                            }
                        } elseif (!empty($currChar) && (empty($char) && $char !== '0')) {
                            continue;
                        }
                        $Template->getGrid()->set($newY, $newX, $char);
                    } else {
                        throw new \Exception(sprintf('y: %d x: %d, invalid coordinates', $newY, $newX));
                    }
                }
            }
        });
    }

    /**
     * Scan the template files lines for occurences of configured data
     * variables and replace them with their values.
     * 
     * @return array
     */
    private function replaceVariables(ViewTemplate $Template)
    {
        foreach ($Template->getVariables() as $key => $coords) {
            $value = '';
            if (isset($this->data[$key])) {
                $value = $this->data[$key];
            }

            if (!is_string($value)) {
                if (!is_scalar($value)) {
                    $value = gettype($value);
                } else {
                    $value = (string) $value;
                }
            }

            // Replace the "$variable" string with the value in the view
            $lines = explode("\n", $value);
            foreach ($lines as $y => $line) {
                $chars = Str::split($line);
                foreach ($chars as $x => $char) {
                    // if ($char === ' ') $char = null;
                    if ($Template->getGrid()->valid($coords[0] + $y, $coords[1] + $x)) {
                        $Template->getGrid()->set($coords[0] + $y, $coords[1] + $x, $char);
                    }
                }
            }
        }
    }

    /**
     * Replace the section IDs and variables in the template with the configured
     * data and return the result as an array of lines.
     * 
     * @return array
     */
    public function render(int $height, int $width): Grid
    {
        $Template = $this->getFile($height, $width);
        $config = $Template->getHeaders($this->defaults);

        $this->setConfig($config);
        $this->replaceComponents($Template);
        $this->replaceVariables($Template);

        return $Template->getGrid();
    }


    private function borderVariant(): string
    {
        return $this->Config->get('border-style') ?? 'light';
    }

    /**
     * Given the coordinates return the Template File with dimensions that match the closest.
     * 
     * @param int $height
     * @param int $width
     * @return ViewTemplate
     */
    private function getFile(int $height, int $width): ViewTemplate
    {
        $Templates = $this->Templates;

        if ($Templates->count() > 1) {
            $Templates = $Templates->filter(function (ViewTemplate $Template) use ($width) {
                return $Template->width() <= $width;
            });
        }

        if ($Templates->count() > 1) {
            $Templates = $Templates->filter(function (ViewTemplate $Template) use ($height) {
                return $Template->height() <= $height;
            });
        }

        if ($Templates->count() > 1) {
            $aspectRatio = $width / $height;

            $Templates = $Templates->sort(function (ViewTemplate $TemplateA, ViewTemplate $TemplateB) use ($aspectRatio) {
                $diffA = 1 - ($TemplateA->aspectRatio() / $aspectRatio);
                $diffB = 1 - ($TemplateB->aspectRatio() / $aspectRatio);

                // closest to 1
                if ($diffA === $diffB) {
                    return 0;
                }
                return ($diffA > $diffB) ? -1 : 1;
            });
        }

        return $Templates->first();
    }

    public function getComponents(): Collection
    {
        $Components = new Collection();

        foreach ($this->Templates as $Template) {
            $this->variables = $Template->getVariables();

            foreach ($Template->getComponents() as $Component) {
                $componentId = '#' . $Component->name();

                if (isset($this->data[$componentId])) {
                    $Component->setContent($this->data[$componentId]);
                } elseif ($componentId === 'cursor') {
                    $Component->setContent('');
                }

                $Components->push($Component);
            }
        }

        return $Components;
    }

    public function getVariables(): array
    {
        $variables = [];

        foreach ($this->Templates as $Template) {
            $variables = array_merge($variables, $Template->getVariables());
        }

        return $variables;
    }


    public function __get($name)
    {
        if ($name === 'config') {
            return $this->Config;
        }

        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        throw new \Exception(sprintf("%s: unknown property", $name));
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }
}