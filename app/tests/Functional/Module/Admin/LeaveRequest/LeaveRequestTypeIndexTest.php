<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use Symfony\Component\DomCrawler\Crawler;

beforeEach(function (): void {
    $this->client = static::createClient();
    $this->em = static::getContainer()->get('doctrine')->getManager();

    $this->admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
});

it('labels the add button on the leave types page with the translated text', function (): void {
    $this->client->loginUser($this->admin);
    $crawler = $this->client->request('GET', '/app/dashboard/leave-request-type');

    $labels = $crawler->filter('.action-new')->each(fn (Crawler $node): string => trim($node->text()));

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and(array_values(array_unique($labels)))->toBe(['Add a new type']);
});
