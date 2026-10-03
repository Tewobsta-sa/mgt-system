<?php

namespace App\Services;

use App\Models\Student;

class StudentIdGenerator
{
    /**
     * Return the next sequential student ID for a prefix (and round for DIS).
     * Fetches only the single highest existing ID instead of scanning all of them.
     */
    public static function next(string $prefix, ?string $round = null): string
    {
        $base = "{$prefix}/";

        if ($prefix === 'DIS') {
            if (!$round) {
                throw new \InvalidArgumentException('Distance track requires a designated round (ዙር).');
            }
            $base = "{$prefix}/" . trim($round) . "/";
        }

        // All matching rows share the same base, so ordering by string length
        // then lexically yields the row with the numerically largest suffix.
        $last = Student::where('student_id', 'like', "{$base}%")
            ->orderByRaw('LENGTH(student_id) DESC')
            ->orderBy('student_id', 'desc')
            ->value('student_id');

        $suffix = $last ? substr($last, strlen($base)) : '0';
        $nextSeq = is_numeric($suffix) ? ((int) $suffix) + 1 : 1;

        do {
            $formattedId = sprintf('%s%03d', $base, $nextSeq);
            $nextSeq++;
        } while (Student::where('student_id', $formattedId)->exists());

        return $formattedId;
    }
}
