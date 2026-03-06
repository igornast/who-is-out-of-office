<?php

declare(strict_types=1);

use App\Module\Admin\Form\DataTransformerm\DateRangeToStartEndTransformer;

beforeEach(function (): void {
    $this->transformer = new DateRangeToStartEndTransformer();
});

it('transforms date array to string', function () {
    $value = [
        'start' => new DateTimeImmutable('2025-03-01'),
        'end' => new DateTimeImmutable('2025-03-15'),
    ];

    $result = $this->transformer->transform($value);

    expect($result)->toBe('2025-03-01 to 2025-03-15');
});

it('transforms to empty string when start date is null', function () {
    $value = ['start' => null, 'end' => new DateTimeImmutable('2025-03-15')];

    $result = $this->transformer->transform($value);

    expect($result)->toBe('');
});

it('transforms to empty string when end date is null', function () {
    $value = ['start' => new DateTimeImmutable('2025-03-01'), 'end' => null];

    $result = $this->transformer->transform($value);

    expect($result)->toBe('');
});

it('transforms to empty string when both dates are null', function () {
    $value = ['start' => null, 'end' => null];

    $result = $this->transformer->transform($value);

    expect($result)->toBe('');
});

it('reverse transforms date range string to array', function () {
    $result = $this->transformer->reverseTransform('2025-03-01 to 2025-03-15');

    expect($result['start']->format('Y-m-d'))->toBe('2025-03-01')
        ->and($result['end']->format('Y-m-d'))->toBe('2025-03-15');
});

it('reverse transforms single date to same start and end', function () {
    $result = $this->transformer->reverseTransform('2025-03-01');

    expect($result['start']->format('Y-m-d'))->toBe('2025-03-01')
        ->and($result['end']->format('Y-m-d'))->toBe('2025-03-01');
});

it('reverse transforms null to empty array', function () {
    $result = $this->transformer->reverseTransform(null);

    expect($result)->toBe([]);
});
