<?php

declare(strict_types=1);

namespace App\Module\Admin\Service;

use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CalendarAvatarUrlResolver
{
    public function __construct(
        private readonly Packages $packages,
        #[Autowire('%profile_images_base_path%')]
        private readonly string $profileImagesBasePath,
    ) {
    }

    public function resolve(?string $rawProfileImageUrl): ?string
    {
        if (null === $rawProfileImageUrl || '' === $rawProfileImageUrl) {
            return null;
        }

        if (str_starts_with($rawProfileImageUrl, 'http://') || str_starts_with($rawProfileImageUrl, 'https://')) {
            return $rawProfileImageUrl;
        }

        return $this->packages->getUrl(sprintf('%s/%s', $this->profileImagesBasePath, $rawProfileImageUrl));
    }
}
