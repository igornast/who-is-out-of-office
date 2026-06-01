<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Query;

use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\OrganizationNodeDTO;

class GetOrganizationTreeQueryHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @return list<OrganizationNodeDTO>
     */
    public function handle(): array
    {
        $users = $this->userRepository->findAllActive();

        /** @var array<string, OrganizationNodeDTO> $nodesById */
        $nodesById = [];
        foreach ($users as $user) {
            $nodesById[$user->id] = new OrganizationNodeDTO(user: $user);
        }

        /** @var list<OrganizationNodeDTO> $roots */
        $roots = [];
        foreach ($users as $user) {
            $node = $nodesById[$user->id];

            if (null !== $user->managerId && isset($nodesById[$user->managerId])) {
                $nodesById[$user->managerId]->children[] = $node;
                continue;
            }

            $roots[] = $node;
        }

        foreach ($nodesById as $node) {
            $this->sortNodes($node->children);
        }

        $this->sortNodes($roots);

        return $roots;
    }

    /**
     * @param list<OrganizationNodeDTO> $nodes
     */
    private function sortNodes(array &$nodes): void
    {
        usort($nodes, static function (OrganizationNodeDTO $a, OrganizationNodeDTO $b): int {
            $firstNameCompare = strcasecmp($a->user->firstName, $b->user->firstName);

            if (0 !== $firstNameCompare) {
                return $firstNameCompare;
            }

            return strcasecmp($a->user->lastName, $b->user->lastName);
        });
    }
}
