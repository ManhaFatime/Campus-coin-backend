<?php

declare(strict_types=1);

function validEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function positiveAmount(mixed $value): ?float
{
    if (!is_numeric($value)) {
        return null;
    }

    $amount = round((float) $value, 2);
    return $amount > 0 ? $amount : null;
}

function nonNegativeAmount(mixed $value): ?float
{
    if (!is_numeric($value)) {
        return null;
    }

    $amount = round((float) $value, 2);
    return $amount >= 0 ? $amount : null;
}

function validDate(string $date): bool
{
    $object = DateTime::createFromFormat('Y-m-d', $date);
    return $object !== false && $object->format('Y-m-d') === $date;
}

function nextRecurringDate(string $date, string $frequency): string
{
    $source = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    if (!$source) {
        return $date;
    }

    if ($frequency === 'weekly') {
        return $source->modify('+7 days')->format('Y-m-d');
    }

    $day = (int) $source->format('d');

    if ($frequency === 'yearly') {
        $targetYear = (int) $source->format('Y') + 1;
        $targetMonth = (int) $source->format('m');

        $firstDay = new DateTimeImmutable(
            sprintf('%04d-%02d-01', $targetYear, $targetMonth)
        );

        $targetDay = min(
            $day,
            (int) $firstDay->format('t')
        );

        return sprintf(
            '%04d-%02d-%02d',
            $targetYear,
            $targetMonth,
            $targetDay
        );
    }

    /*
     * Monthly recurrence keeps the original day where possible.
     * Dates such as Jan 31 are clamped to Feb 28/29 instead of
     * PHP's default behaviour of jumping into March.
     */
    $firstDayNextMonth =
        $source->modify('first day of next month');

    $targetDay = min(
        $day,
        (int) $firstDayNextMonth->format('t')
    );

    return $firstDayNextMonth
        ->setDate(
            (int) $firstDayNextMonth->format('Y'),
            (int) $firstDayNextMonth->format('m'),
            $targetDay
        )
        ->format('Y-m-d');
}

function monthStart(string $month): ?string
{
    if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d', $month . '-01');
    return $date ? $date->format('Y-m-01') : null;
}
