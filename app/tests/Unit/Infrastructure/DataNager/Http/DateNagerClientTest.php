<?php

declare(strict_types=1);

use App\Infrastructure\DataNager\Http\DateNagerClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

beforeEach(function (): void {
    $this->httpClient = mock(HttpClientInterface::class);
    $this->client = new DateNagerClient($this->httpClient);
});

it('fetches holidays for country and year', function (): void {
    $holidays = [
        ['date' => '2025-01-01', 'localName' => 'Nowy Rok', 'name' => "New Year's Day", 'countryCode' => 'PL'],
        ['date' => '2025-12-25', 'localName' => 'Boże Narodzenie', 'name' => 'Christmas Day', 'countryCode' => 'PL'],
    ];

    $response = mock(ResponseInterface::class);
    $response->allows('getStatusCode')->andReturn(200);
    $response->allows('toArray')->andReturn($holidays);

    $this->httpClient
        ->expects('request')
        ->once()
        ->with('GET', 'https://date.nager.at/api/v3/PublicHolidays/2025/PL')
        ->andReturn($response);

    $result = $this->client->fetchHolidays('pl', 2025);

    expect($result)->toBe($holidays);
});

it('uppercases country code in holidays URL', function (): void {
    $response = mock(ResponseInterface::class);
    $response->allows('getStatusCode')->andReturn(200);
    $response->allows('toArray')->andReturn([]);

    $this->httpClient
        ->expects('request')
        ->once()
        ->with('GET', 'https://date.nager.at/api/v3/PublicHolidays/2025/DE')
        ->andReturn($response);

    $this->client->fetchHolidays('de', 2025);
});

it('fetches available countries', function (): void {
    $countries = [
        ['countryCode' => 'PL', 'name' => 'Poland'],
        ['countryCode' => 'DE', 'name' => 'Germany'],
    ];

    $response = mock(ResponseInterface::class);
    $response->allows('getStatusCode')->andReturn(200);
    $response->allows('toArray')->andReturn($countries);

    $this->httpClient
        ->expects('request')
        ->once()
        ->with('GET', 'https://date.nager.at/api/v3/AvailableCountries')
        ->andReturn($response);

    $result = $this->client->fetchAvailableCountries();

    expect($result)->toBe($countries);
});

it('throws RuntimeException on non-200 status code for holidays', function (): void {
    $response = mock(ResponseInterface::class);
    $response->allows('getStatusCode')->andReturn(404);

    $this->httpClient->allows('request')->andReturn($response);

    $this->client->fetchHolidays('XX', 2025);
})->throws(RuntimeException::class, 'Failed to fetch public holidays from Nager.Date. (HTTP 404)');

it('throws RuntimeException on non-200 status code for countries', function (): void {
    $response = mock(ResponseInterface::class);
    $response->allows('getStatusCode')->andReturn(500);

    $this->httpClient->allows('request')->andReturn($response);

    $this->client->fetchAvailableCountries();
})->throws(RuntimeException::class, 'Failed to fetch available countries from Nager.Date. (HTTP 500)');

it('throws RuntimeException when http client throws exception', function (): void {
    $this->httpClient
        ->allows('request')
        ->andThrow(new Symfony\Component\HttpClient\Exception\TransportException('Connection failed'));

    $this->client->fetchHolidays('PL', 2025);
})->throws(RuntimeException::class, 'Failed to fetch public holidays from Nager.Date.');
