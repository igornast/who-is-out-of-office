<?php

declare(strict_types=1);

namespace App\Module\Admin\Form\DataTransformerm;

use Symfony\Component\Form\DataTransformerInterface;

/**
 * @implements DataTransformerInterface<array{'start': \DateTimeImmutable, 'end': \DateTimeImmutable}, string>
 */
class DateRangeToStartEndTransformer implements DataTransformerInterface
{
    /**
     * @param array{'start': \DateTimeImmutable|null, 'end': \DateTimeImmutable|null} $value
     */
    public function transform(mixed $value): ?string
    {
        $startDate = $value['start'] ?? null;
        $endDate = $value['end'] ?? null;

        if ($startDate instanceof \DateTimeImmutable && $endDate instanceof \DateTimeImmutable) {
            return $startDate->format('Y-m-d').' to '.$endDate->format('Y-m-d');
        }

        return '';

    }

    /**
     * @param ?string $value
     *
     * @return array{}|array{'start': \DateTimeImmutable, 'end': \DateTimeImmutable}
     */
    public function reverseTransform(mixed $value): array
    {
        if (null === $value) {
            return [];
        }

        $start = $value;
        $end = $value;

        if (str_contains($value, 'to')) {
            [$start, $end] = array_map('trim', explode('to', $value));
        }

        $startDate = new \DateTimeImmutable($start);
        $endDate = new \DateTimeImmutable($end);

        return ['start' => $startDate, 'end' => $endDate];
    }
}
