<?php declare(strict_types=1);

namespace PhpCli;

class Calendar {

    public const GREGORIAN = CAL_GREGORIAN;

    public const JULIAN = CAL_JULIAN;

    public const JEWISH = CAL_JEWISH;

    public const FRENCH = CAL_FRENCH;

    public const JANUARY = 1;

    public const FEBRUARY = 2;

    public const MARCH = 3;

    public const APRIL = 4;

    public const MAY = 5;

    public const JUNE = 6;

    public const JULY = 7;

    public const AUGUST = 8;

    public const SEPTEMBER = 9;

    public const OCTOBER = 10;

    public const NOVEMBER = 11;

    public const DECEMBER = 12;

    private int $format;

    private int $month;

    private int $year;

    public function __construct(int $month = null, int $year = null, int $format = CAL_GREGORIAN)
    {
        if ($month === null) {
            $month = (int) date('n');
        }
        if ($year === null) {
            $year = (int) date('Y');
        }

        $this->setFormat($format);
        $this->setMonth($month)->setYear($year);
    }

    public function addMonth(): self
    {
        if ($this->month === 12) {
            $this->addYear();
            $this->setMonth(1);
        } else {
            $this->month++;
        }

        return $this;
    }

    public function addMonths(int $months = 2): self
    {
        while ($months > 0) {
            $this->addMonth();
            $months--;
        }

        return $this;
    }

    public function addYear(): self
    {
        $this->addYears(1);

        return $this;
    }

    public function addYears(int $years = 2): self
    {
        $this->year += $years;

        return $this;
    }

    public function days(): int
    {
        return cal_days_in_month($this->format, $this->month, $this->year);
    }

    public function month(): int
    {
        return $this->month;
    }

    public function monthStr(): string
    {
        $info = cal_info($this->format);

        return $info['months'][$this->month];
    }

    public function monthStrAbbrev(): string
    {
        $info = cal_info($this->format);

        return $info['abbrevmonths'][$this->month];
    }

    public function monthsInYear(): int
    {
        $info = cal_info($this->format);

        return count($info['months']);
    }

    public function setFormat(int $format)
    {
        if ($format < 0 || $format > 3) {
            throw new \InvalidArgumentException('Calendar format must be an integer between 0 and 3');
        }
        $this->format = $format;
    }

    public function setMonth(int $month): self
    {
        $max = $this->monthsInYear();
        if ($month < 1 || $month > $max) {
            throw new \InvalidArgumentException(sprintf('Month must be an integer between 1 and %d', $max));
        }
        $this->month = $month;

        return $this;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function year(): int
    {
        return $this->year;
    }

    public function __toString(): string
    {
        return sprintf('%s %d', $this->monthStr(), $this->year);
    }
}