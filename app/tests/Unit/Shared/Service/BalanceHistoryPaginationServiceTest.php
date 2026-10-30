<?php

declare(strict_types=1);

use App\Shared\Service\BalanceHistoryPaginationService;
use App\Tests\_fixtures\Shared\DTO\LeavePeriodDTOFixture;

beforeEach(function (): void {
    $this->service = new BalanceHistoryPaginationService();
    $this->closedPeriod = LeavePeriodDTOFixture::create(['id' => 'closed', 'closedAt' => new DateTimeImmutable('-1 month')]);
    $this->olderClosedPeriod = LeavePeriodDTOFixture::create(['id' => 'older', 'closedAt' => new DateTimeImmutable('-13 months')]);
    $this->openPeriod = LeavePeriodDTOFixture::create(['id' => 'open']);
});

it('returns the requested period', function (): void {
    expect($this->service->resolvePeriod([$this->openPeriod, $this->closedPeriod], 'closed'))->toBe($this->closedPeriod);
});

it('falls back to the open period when the requested one is unknown', function (): void {
    expect($this->service->resolvePeriod([$this->closedPeriod, $this->openPeriod], 'unknown'))->toBe($this->openPeriod);
});

it('falls back to the first period when none is open', function (): void {
    expect($this->service->resolvePeriod([$this->closedPeriod, $this->olderClosedPeriod], ''))->toBe($this->closedPeriod);
});

it('accepts a positive page number', function (mixed $requestedPage, int $expectedPage): void {
    expect($this->service->resolvePage($requestedPage))->toBe($expectedPage);
})->with([
    'integer' => [3, 3],
    'numeric string' => ['2', 2],
    'largest allowed page' => [(string) intdiv(PHP_INT_MAX, 50), intdiv(PHP_INT_MAX, 50)],
]);

it('rejects an invalid page number', function (mixed $requestedPage): void {
    expect($this->service->resolvePage($requestedPage))->toBeNull();
})->with([
    'zero' => [0],
    'negative' => ['-1'],
    'text' => ['abc'],
    'missing' => [null],
    'past the offset limit' => [(string) (intdiv(PHP_INT_MAX, 50) + 1)],
]);

it('pages fifty entries at a time', function (): void {
    expect($this->service->pageSize())->toBe(50);
});
