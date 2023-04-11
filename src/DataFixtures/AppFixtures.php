<?php

namespace App\DataFixtures;

use App\Entity\Client;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    )
    {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create();

        $user = new Client();
        $user->setEmail("test@test.fr");
        $user->setRoles(["ROLE_USER"]);
        $user->setPassword($this->passwordHasher->hashPassword($user, "password"));
        $manager->persist($user);

        $manager->flush();

        for ($i = 0; $i < 100; $i++) {
            $product = new Product();
            $product->setName($faker->name());
            $product->setDescription($faker->text(200));
            $product->setPrice($faker->randomFloat(2, 0, 1000));
            $manager->persist($product);
            $manager->flush();
        }
    }
}
