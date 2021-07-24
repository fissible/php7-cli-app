<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use PhpCli\Calendar;

final class CalendarTest extends TestCase
{
    public function testAddMonth()
    {
        $C = new Calendar(11);
        $C->addMonth();

        $this->assertEquals(12, $C->month());
    }

    public function testAddMonths()
    {
        $C = new Calendar(11);
        $C->addMonths(4);

        $this->assertEquals(3, $C->month());
    }

    public function testAddYear()
    {
        $C = new Calendar(1, 2009);
        $C->addYear();

        $this->assertEquals(2010, $C->year());
    }

    public function testAddYears()
    {
        $C = new Calendar(1, 2009);
        $C->addYears(3);

        $this->assertEquals(2012, $C->year());
    }

    public function testDays()
    {
        // non lear year
        $C = new Calendar(2, 2011);
        $expected = cal_days_in_month(CAL_GREGORIAN, 2, 2011);
        $actual = $C->days();

        $this->assertEquals($expected, $actual);

        // leap year
        $C = new Calendar(2, 2012);
        $expected = cal_days_in_month(CAL_GREGORIAN, 2, 2012);
        $actual = $C->days();

        $this->assertEquals($expected, $actual);
    }

    public function testMonth()
    {
        $C = new Calendar(2, 2012);

        $this->assertEquals(2, $C->month());
    }

    public function testMonthStr()
    {
        $C = new Calendar(2, 2012);

        $this->assertEquals('February', $C->monthStr());
    }

    public function testMonthStrAbbrev()
    {
        $C = new Calendar(2, 2012);

        $this->assertEquals('Feb', $C->monthStrAbbrev());
    }

    public function testMonthsInYear()
    {
        $C = new Calendar();

        $this->assertEquals(12, $C->monthsInYear());
    }

    public function testSetFormat()
    {
        $C = new Calendar();
        $C->setFormat(Calendar::FRENCH);

        $this->assertEquals(13, $C->monthsInYear());
    }

    public function testSetMonth()
    {
        $C = new Calendar(2);
        $C->setMonth(4);

        $this->assertEquals(4, $C->month());
    }

    public function testSetYear()
    {
        $C = new Calendar(2, 2020);
        $C->setYear(2022);

        $this->assertEquals(2022, $C->year());
    }

    public function testYear()
    {
        $C = new Calendar(2, 2012);

        $this->assertEquals(2012, $C->year());
    }

    public function testToString()
    {
        $C = new Calendar(2, 2012);

        $this->assertEquals('February 2012', $C.'');
    }
}