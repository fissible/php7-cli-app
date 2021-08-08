<?php declare(strict_types=1);

namespace PhpCli;

class TableHeader
{
    public const ALIGN_CENTER = 'C';

    public const ALIGN_LEFT = 'L';

    public const ALIGN_RIGHT = 'R';

    protected string $display;

    protected string $alignment;

    private ?string $column;

    public function __construct(string $display, ?string $column = null, ?string $alignment = self::ALIGN_LEFT)
    {
        $this->setAlignment($alignment);
        $this->setDisplay($display);
        $this->setColumn($column);
    }

    public function alignment(): string
    {
        return $this->alignment;
    }

    public function column(): string
    {
        return $this->column ?? null;
    }

    public function display(): string
    {
        return $this->display;
    }

    public function setAlignment(?string $alignment = null): self
    {
        if (!is_null($alignment)) {
            if (!in_array($alignment, [self::ALIGN_CENTER, self::ALIGN_LEFT, self::ALIGN_RIGHT])) {
                throw new \InvalidArgumentException();
            }
            $this->alignment = $alignment;
        }
        
        return $this;
    }

    /**
     * @param string|null $column
     */
    public function setColumn(?string $column = null): self
    {
        $this->column = $column;
        
        return $this;
    }

    public function setDisplay(string $display): self
    {
        if (!empty($display) && $display !== ' ') {
            if ($display[0] === ' ' && $display[-1] === ' ') {
                $this->setAlignment(self::ALIGN_CENTER);
            } elseif ($display[0] === ' ') {
                $this->setAlignment(self::ALIGN_RIGHT);
            } elseif ($display[-1] === ' ') {
                $this->setAlignment(self::ALIGN_LEFT);
            }
        }

        $this->display = trim($display);

        return $this;
    }

    public function __toString(): string
    {
        return $this->display;
    }
}