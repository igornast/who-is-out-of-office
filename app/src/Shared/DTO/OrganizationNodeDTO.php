<?php

declare(strict_types=1);

namespace App\Shared\DTO;

class OrganizationNodeDTO
{
    /**
     * @param list<OrganizationNodeDTO> $children
     */
    public function __construct(
        public UserDTO $user,
        public array $children = [],
    ) {
    }
}
