<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class RemoveProfileImageCommandHandler
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/%profile_images_base_path%')]
        private readonly string $uploadDirectory,
    ) {
    }

    public function handle(?string $currentProfileImageUrl): void
    {
        if (null === $currentProfileImageUrl
            || str_starts_with($currentProfileImageUrl, 'http://')
            || str_starts_with($currentProfileImageUrl, 'https://')
        ) {
            return;
        }

        $filePath = sprintf('%s/%s', $this->uploadDirectory, basename($currentProfileImageUrl));
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
