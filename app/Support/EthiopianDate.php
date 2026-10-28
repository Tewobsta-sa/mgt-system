<?php

namespace App\Support;

/**
 * Ethiopian calendar helpers, ported from the frontend converter
 * (church-management/src/utils/ethiopianDate.js) so both ends agree.
 */
class EthiopianDate
{
    /**
     * Gregorian -> Ethiopian conversion using Julian Day Number math.
     *
     * @return array{year:int, month:int, day:int}
     */
    public static function fromGregorian(\DateTimeInterface $date): array
    {
        $gy = (int) $date->format('Y');
        $gm = (int) $date->format('n');
        $gd = (int) $date->format('j');

        $a = intdiv(14 - $gm, 12);
        $y = $gy + 4800 - $a;
        $m = $gm + 12 * $a - 3;
        $jdn = $gd
            + intdiv(153 * $m + 2, 5)
            + 365 * $y
            + intdiv($y, 4)
            - intdiv($y, 100)
            + intdiv($y, 400)
            - 32045;

        $offset = 1723856;
        $diff = $jdn - $offset;
        $r = $diff % 1461;
        if ($r < 0) {
            $r += 1461;
        }
        $n = ($r % 365) + 365 * intdiv($r, 1460);

        return [
            'year' => 4 * intdiv($diff, 1461) + intdiv($r, 365) - intdiv($r, 1460),
            'month' => intdiv($n, 30) + 1,
            'day' => ($n % 30) + 1,
        ];
    }

    /**
     * Ethiopian -> Gregorian (Y-m-d). Searches the small Gregorian window
     * around the Ethiopian new year, consistent with the JS converter.
     */
    public static function toGregorian(int $ey, int $em, int $ed): string
    {
        $start = new \DateTimeImmutable(sprintf('%04d-09-01', $ey + 7));
        for ($offset = -30; $offset <= 400; $offset++) {
            $candidate = $start->modify("{$offset} days");
            $conv = self::fromGregorian($candidate);
            if ($conv['year'] === $ey && $conv['month'] === $em && $conv['day'] === $ed) {
                return $candidate->format('Y-m-d');
            }
        }

        return sprintf('%04d-09-11', $ey + 7);
    }

    /**
     * Gregorian Y-m-d of Meskerem 1 of the Ethiopian year containing $ref.
     * Used as the current academic year boundary (Meskerem -> Pagume).
     */
    public static function academicYearStart(?\DateTimeInterface $ref = null): string
    {
        $eth = self::fromGregorian($ref ?? new \DateTimeImmutable('today'));
        return self::toGregorian($eth['year'], 1, 1);
    }
}
