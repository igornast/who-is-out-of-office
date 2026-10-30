<?php

declare(strict_types=1);

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Nelmio\Alice\Loader\NativeLoader;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $loader = new NativeLoader();
        $objectSet = $loader->loadFile(__DIR__.'/fixtures.yaml')->getObjects();

        foreach ($objectSet as $object) {
            $manager->persist($object);
        }

        $builder = new BalanceLedgerFixtureBuilder();
        foreach ($builder->build($objectSet) as $entity) {
            $manager->persist($entity);
        }

        $manager->flush();
    }
}
