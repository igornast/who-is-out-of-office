<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Service\HolidayStatusTextBuilder;
use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayDTOFixture;

beforeEach(function () {
    $this->builder = new HolidayStatusTextBuilder();
});

it('sorts global before regional and alphabetically within each group', function () {
    $holidays = [
        PublicHolidayDTOFixture::create(['description' => 'Zeta', 'isGlobal' => false]),
        PublicHolidayDTOFixture::create(['description' => 'Beta', 'isGlobal' => true]),
        PublicHolidayDTOFixture::create(['description' => 'Alpha', 'isGlobal' => false]),
        PublicHolidayDTOFixture::create(['description' => 'Alpha', 'isGlobal' => true]),
    ];

    $sorted = $this->builder->sortForStatus($holidays);

    expect(array_map(
        static fn (PublicHolidayDTO $holiday): string => sprintf('%s/%s', $holiday->description, $holiday->isGlobal ? 'g' : 'r'),
        $sorted,
    ))->toBe(['Alpha/g', 'Beta/g', 'Alpha/r', 'Zeta/r']);
});

it('returns a single name unchanged', function () {
    $holiday = PublicHolidayDTOFixture::create(['description' => 'Christmas Day']);

    expect($this->builder->buildText([$holiday]))->toBe('Christmas Day');
});

it('joins distinct names in the given order', function () {
    $holidays = [
        PublicHolidayDTOFixture::create(['description' => 'Ostermontag']),
        PublicHolidayDTOFixture::create(['description' => 'Ostermontag']),
        PublicHolidayDTOFixture::create(['description' => 'Annunciation']),
    ];

    expect($this->builder->buildText($holidays))->toBe('Ostermontag, Annunciation');
});

it('keeps whole names that fit and appends an ellipsis', function () {
    $holidays = [
        PublicHolidayDTOFixture::create(['description' => str_repeat('a', 40)]),
        PublicHolidayDTOFixture::create(['description' => str_repeat('b', 40)]),
        PublicHolidayDTOFixture::create(['description' => str_repeat('c', 40)]),
    ];

    $result = $this->builder->buildText($holidays);

    expect($result)->toBe(sprintf('%s, %s…', str_repeat('a', 40), str_repeat('b', 40)))
        ->and(mb_strlen($result))->toBe(83);
});

it('cuts a single overlong name to 99 characters plus ellipsis', function () {
    $name = str_repeat('a', 150);
    $holiday = PublicHolidayDTOFixture::create(['description' => $name]);

    $result = $this->builder->buildText([$holiday]);

    expect($result)->toBe(mb_substr($name, 0, 99).'…')
        ->and(mb_strlen($result))->toBe(100);
});

it('counts characters not bytes when truncating', function () {
    $name1 = str_repeat('α', 45);
    $name2 = str_repeat('β', 45);

    expect(mb_strlen($name1))->toBe(45)
        ->and(strlen($name1))->toBe(90)
        ->and(mb_strlen($name2))->toBe(45)
        ->and(strlen($name2))->toBe(90);

    $result = $this->builder->buildText([
        PublicHolidayDTOFixture::create(['description' => $name1]),
        PublicHolidayDTOFixture::create(['description' => $name2]),
    ]);

    expect($result)->toBe(sprintf('%s, %s', $name1, $name2))
        ->and(mb_strlen($result))->toBe(92)
        ->and($result)->not->toContain('…');
});

it('counts the ellipsis against the maximum length', function () {
    $name48 = str_repeat('a', 48);
    $name50 = str_repeat('b', 50);
    $name10 = str_repeat('c', 10);

    $result = $this->builder->buildText([
        PublicHolidayDTOFixture::create(['description' => $name48]),
        PublicHolidayDTOFixture::create(['description' => $name50]),
        PublicHolidayDTOFixture::create(['description' => $name10]),
    ]);

    expect($result)->toBe($name48.'…')
        ->and(mb_strlen($result))->toBe(49);
});

it('keeps names when the ellipsis brings the result to exactly 100 characters', function () {
    $name47 = str_repeat('a', 47);
    $name50 = str_repeat('b', 50);
    $name10 = str_repeat('c', 10);

    $result = $this->builder->buildText([
        PublicHolidayDTOFixture::create(['description' => $name47]),
        PublicHolidayDTOFixture::create(['description' => $name50]),
        PublicHolidayDTOFixture::create(['description' => $name10]),
    ]);

    expect($result)->toBe(sprintf('%s, %s…', $name47, $name50))
        ->and(mb_strlen($result))->toBe(100);
});

it('keeps valid UTF-8 when truncating multibyte names', function () {
    $name1 = str_repeat('α', 40);
    $name2 = str_repeat('β', 40);
    $name3 = str_repeat('γ', 40);

    $result = $this->builder->buildText([
        PublicHolidayDTOFixture::create(['description' => $name1]),
        PublicHolidayDTOFixture::create(['description' => $name2]),
        PublicHolidayDTOFixture::create(['description' => $name3]),
    ]);

    expect($result)->toBe(sprintf('%s, %s…', $name1, $name2))
        ->and(mb_strlen($result))->toBe(83)
        ->and(mb_check_encoding($result, 'UTF-8'))->toBeTrue();
});

it('keeps valid UTF-8 when cutting a single multibyte name', function () {
    $name = str_repeat('α', 150);

    $result = $this->builder->buildText([
        PublicHolidayDTOFixture::create(['description' => $name]),
    ]);

    expect($result)->toBe(mb_substr($name, 0, 99).'…')
        ->and(mb_strlen($result))->toBe(100)
        ->and(str_ends_with($result, '…'))->toBeTrue()
        ->and(mb_check_encoding($result, 'UTF-8'))->toBeTrue();
});

it('joins the Greek example from the spec in code point order', function () {
    $holidays = $this->builder->sortForStatus([
        PublicHolidayDTOFixture::create(['description' => 'Ευαγγελισμός της Θεοτόκου', 'isGlobal' => true]),
        PublicHolidayDTOFixture::create(['description' => 'Εικοστή Πέμπτη Μαρτίου', 'isGlobal' => true]),
    ]);

    expect($this->builder->buildText($holidays))->toBe('Εικοστή Πέμπτη Μαρτίου, Ευαγγελισμός της Θεοτόκου');
});

it('collapses duplicate regional entries of one holiday after sorting', function () {
    $holidays = $this->builder->sortForStatus([
        PublicHolidayDTOFixture::create(['description' => 'Zeta', 'isGlobal' => false]),
        PublicHolidayDTOFixture::create(['description' => 'Alpha', 'isGlobal' => false]),
        PublicHolidayDTOFixture::create(['description' => 'Alpha', 'isGlobal' => false]),
    ]);

    expect($this->builder->buildText($holidays))->toBe('Alpha, Zeta');
});

it('shows a name that is both global and regional once at its global position', function () {
    $holidays = $this->builder->sortForStatus([
        PublicHolidayDTOFixture::create(['description' => 'Alpha', 'isGlobal' => false]),
        PublicHolidayDTOFixture::create(['description' => 'Beta', 'isGlobal' => false]),
        PublicHolidayDTOFixture::create(['description' => 'Beta', 'isGlobal' => true]),
        PublicHolidayDTOFixture::create(['description' => 'Gamma', 'isGlobal' => false]),
    ]);

    expect($this->builder->buildText($holidays))->toBe('Beta, Alpha, Gamma');
});

it('does not reorder the array passed to sortForStatus', function () {
    $holidays = [
        PublicHolidayDTOFixture::create(['description' => 'Zeta', 'isGlobal' => false]),
        PublicHolidayDTOFixture::create(['description' => 'Alpha', 'isGlobal' => true]),
    ];

    $sorted = $this->builder->sortForStatus($holidays);

    expect($holidays[0]->description)->toBe('Zeta')
        ->and($sorted[0]->description)->toBe('Alpha');
});

it('keeps the joined text when it is exactly 100 characters', function () {
    $name1 = str_repeat('a', 49);
    $name2 = str_repeat('b', 49);

    $result = $this->builder->buildText([
        PublicHolidayDTOFixture::create(['description' => $name1]),
        PublicHolidayDTOFixture::create(['description' => $name2]),
    ]);

    expect($result)->toBe(sprintf('%s, %s', $name1, $name2))
        ->and(mb_strlen($result))->toBe(100)
        ->and($result)->not->toContain('…');
});

it('returns a single name of exactly 100 characters unchanged', function () {
    $name = str_repeat('a', 100);

    $result = $this->builder->buildText([
        PublicHolidayDTOFixture::create(['description' => $name]),
    ]);

    expect($result)->toBe($name);
});

it('cuts a single name of 101 characters to 99 plus ellipsis', function () {
    $name = str_repeat('a', 101);

    $result = $this->builder->buildText([
        PublicHolidayDTOFixture::create(['description' => $name]),
    ]);

    expect($result)->toBe(mb_substr($name, 0, 99).'…')
        ->and(mb_strlen($result))->toBe(100);
});

it('cuts a first name that alone is too long even when more names follow', function () {
    $first = str_repeat('a', 100);

    $result = $this->builder->buildText([
        PublicHolidayDTOFixture::create(['description' => $first]),
        PublicHolidayDTOFixture::create(['description' => 'Second']),
    ]);

    expect($result)->toBe(mb_substr($first, 0, 99).'…')
        ->and(mb_strlen($result))->toBe(100);
});
