<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO\Slack;

use App\Shared\DTO\Slack\SlackAdminTokenDTO;
use App\Tests\_fixtures\FixtureInterface;
use Faker\Factory;

class SlackAdminTokenDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): SlackAdminTokenDTO
    {
        return new SlackAdminTokenDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        $faker = Factory::create();

        return [
            'id' => $faker->uuid(),
            'plainToken' => 'xoxp-'.$faker->bothify('########-########-########'),
            'slackUserId' => 'U'.strtoupper($faker->bothify('#########')),
            'createdAt' => new \DateTimeImmutable(),
        ];
    }
}
