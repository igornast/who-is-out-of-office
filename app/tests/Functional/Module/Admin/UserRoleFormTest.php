<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Enum\RoleEnum;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DomCrawler\Form;

dataset('roleFormTestCombinations', [
    'none' => [[]],
    'admin' => [[RoleEnum::Admin->value]],
    'manager' => [[RoleEnum::Manager->value]],
    'admin and manager' => [[RoleEnum::Admin->value, RoleEnum::Manager->value]],
]);

beforeEach(function (): void {
    $this->client = static::createClient();
    $this->em = static::getContainer()->get(EntityManagerInterface::class);
    $this->admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
    $this->client->loginUser($this->admin);
});

function createRoleFormTestUser(EntityManagerInterface $em, array $roles): User
{
    $user = new User(
        id: Uuid::uuid4(),
        firstName: 'Role',
        lastName: sprintf('Form %s', Uuid::uuid4()->toString()),
        email: sprintf('roles-%s@example.com', Uuid::uuid4()->toString()),
        password: 'password',
        isActive: true,
        roles: $roles,
    );

    $em->persist($user);
    $em->flush();

    return $user;
}

function setRoleFormTestCheckboxes(Form $form, array $roles): void
{
    foreach ($form['User[roles]'] as $checkbox) {
        in_array($checkbox->availableOptionValues()[0], $roles, true) ? $checkbox->tick() : $checkbox->untick();
    }
}

it('renders administrator and manager checkboxes with translated labels and a help line', function (): void {
    $crawler = $this->client->request('GET', '/app/dashboard/my-organization/new');

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('input[type=checkbox][name="User[roles][]"]')->extract(['value']))
        ->toBe(['ROLE_ADMIN', 'ROLE_MANAGER'])
        ->and($crawler->filter('label[for="User_roles_0"]')->text())->toBe('Administrator')
        ->and($crawler->filter('label[for="User_roles_1"]')->text())->toBe('Manager')
        ->and($crawler->filter('input[type=text][name^="User[roles]"]'))->toHaveCount(0)
        ->and($crawler->filter('#User_roles')->closest('.form-group')->filter('small.form-text.form-help')->text())
        ->toBe('Everyone gets the User role automatically. Administrator doesn’t include Manager, so tick both if someone needs both.');
});

it('creates a user with the chosen roles', function (array $roles): void {
    $email = sprintf('roles-%s@example.com', Uuid::uuid4()->toString());
    $crawler = $this->client->request('GET', '/app/dashboard/my-organization/new');
    $form = $crawler->filter('button.action-saveAndReturn')->form();
    $form['User[email]'] = $email;
    setRoleFormTestCheckboxes($form, $roles);

    $this->client->submit($form);

    $this->em->clear();
    $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

    expect($this->client->getResponse()->isRedirection())->toBeTrue()
        ->and($user)->toBeInstanceOf(User::class)
        ->and($user->roles)->toEqualCanonicalizing([...$roles, RoleEnum::User->value]);
})->with('roleFormTestCombinations');

it('edits a user to the chosen roles', function (array $roles): void {
    $storedRoles = [] === $roles
        ? [RoleEnum::Admin->value, RoleEnum::Manager->value, RoleEnum::User->value]
        : [RoleEnum::User->value];
    $user = createRoleFormTestUser($this->em, $storedRoles);

    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $user->id->toString()));
    $form = $crawler->filter('button.action-saveAndReturn')->form();
    setRoleFormTestCheckboxes($form, $roles);
    $this->client->submit($form);

    $this->em->clear();
    $reloaded = $this->em->find(User::class, $user->id);

    expect($this->client->getResponse()->isRedirection())->toBeTrue()
        ->and($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->roles)->toEqualCanonicalizing([...$roles, RoleEnum::User->value]);
})->with('roleFormTestCombinations');

it('ticks stored roles on edit and keeps them when saved unchanged', function (array $stored): void {
    $user = createRoleFormTestUser($this->em, $stored);
    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $user->id->toString()));

    expect($crawler->filter('input[name="User[roles][]"]:checked')->extract(['value']))
        ->toBe(array_values(array_diff($stored, [RoleEnum::User->value])));

    $form = $crawler->filter('button.action-saveAndReturn')->form();
    $this->client->submit($form);

    $expected = array_values(array_unique([...array_diff($stored, [RoleEnum::User->value]), RoleEnum::User->value]));
    $this->em->clear();
    $reloaded = $this->em->find(User::class, $user->id);

    expect($this->client->getResponse()->isRedirection())->toBeTrue()
        ->and($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->roles)->toEqualCanonicalizing($expected)
        ->and($reloaded->getRoles())->toEqualCanonicalizing($expected);
})->with([
    'admin without stored user role' => [[RoleEnum::Admin->value]],
    'only user role' => [[RoleEnum::User->value]],
    'manager and user' => [[RoleEnum::Manager->value, RoleEnum::User->value]],
]);

it('drops an unknown legacy role on save', function (): void {
    $user = createRoleFormTestUser($this->em, ['ROLE_MANGER', RoleEnum::Admin->value]);
    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $user->id->toString()));

    expect($crawler->filter('input[name="User[roles][]"]:checked')->extract(['value']))
        ->toBe([RoleEnum::Admin->value])
        ->and($this->client->getResponse()->getContent())->not->toContain('ROLE_MANGER');

    $form = $crawler->filter('button.action-saveAndReturn')->form();
    $this->client->submit($form);

    $this->em->clear();
    $reloaded = $this->em->find(User::class, $user->id);

    expect($this->client->getResponse()->getStatusCode())->toBe(302)
        ->and($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->roles)->toEqualCanonicalizing([RoleEnum::Admin->value, RoleEnum::User->value]);
});

it('rejects a forged role value', function (): void {
    $user = createRoleFormTestUser($this->em, [RoleEnum::User->value]);
    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $user->id->toString()));
    $form = $crawler->filter('button.action-saveAndReturn')->form();
    $values = $form->getPhpValues();
    $values['User']['roles'] = ['ROLE_SUPER_ADMIN'];
    $this->client->request($form->getMethod(), $form->getUri(), $values, $form->getPhpFiles());

    $this->em->clear();
    $reloaded = $this->em->find(User::class, $user->id);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($this->client->getResponse()->getContent())->toContain('The selected choice is invalid.')
        ->and($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->roles)->toBe([RoleEnum::User->value]);
});

it('lists the user role on the index for a user stored without it', function (): void {
    $user = createRoleFormTestUser($this->em, [RoleEnum::Admin->value]);
    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization?query=%s', urlencode($user->lastName)));
    $cells = $crawler->filter('td[data-column="roles"]');

    expect($cells)->toHaveCount(1)
        ->and(trim($cells->text()))->toBe('Administrator, User');
});

it('lists translated roles on the index for a manager', function (): void {
    $user = createRoleFormTestUser($this->em, [RoleEnum::Admin->value]);
    $manager = $this->em->getRepository(User::class)->findOneBy(['email' => 'manager@whoisooo.app']);
    $this->client->loginUser($manager);

    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization?query=%s', urlencode($user->lastName)));
    $cells = $crawler->filter('td[data-column="roles"]');

    expect($cells)->toHaveCount(1)
        ->and(trim($cells->text()))->toBe('Administrator, User');
});

it('rejects a mix of a valid and a forged role value and saves nothing', function (): void {
    $user = createRoleFormTestUser($this->em, [RoleEnum::User->value]);
    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $user->id->toString()));
    $form = $crawler->filter('button.action-saveAndReturn')->form();
    $values = $form->getPhpValues();
    $values['User']['roles'] = [RoleEnum::Admin->value, 'ROLE_SUPER_ADMIN'];
    $this->client->request($form->getMethod(), $form->getUri(), $values, $form->getPhpFiles());

    $this->em->clear();
    $reloaded = $this->em->find(User::class, $user->id);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($this->client->getResponse()->getContent())->toContain('The selected choice is invalid.')
        ->and($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->roles)->toBe([RoleEnum::User->value]);
});

it('rejects a forged role value on create and saves no user', function (): void {
    $email = sprintf('roles-%s@example.com', Uuid::uuid4()->toString());
    $crawler = $this->client->request('GET', '/app/dashboard/my-organization/new');
    $form = $crawler->filter('button.action-saveAndReturn')->form();
    $values = $form->getPhpValues();
    $values['User']['email'] = $email;
    $values['User']['roles'] = ['ROLE_SUPER_ADMIN'];
    $this->client->request($form->getMethod(), $form->getUri(), $values, $form->getPhpFiles());

    $this->em->clear();
    $created = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($this->client->getResponse()->getContent())->toContain('The selected choice is invalid.')
        ->and($created)->toBeNull();
});

it('renders only administrator and manager as checkboxes on the edit page', function (): void {
    $user = createRoleFormTestUser($this->em, [RoleEnum::User->value]);
    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $user->id->toString()));

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('input[type=checkbox][name="User[roles][]"]')->extract(['value']))
        ->toBe(['ROLE_ADMIN', 'ROLE_MANAGER'])
        ->and($crawler->filter('label[for="User_roles_0"]')->text())->toBe('Administrator')
        ->and($crawler->filter('label[for="User_roles_1"]')->text())->toBe('Manager')
        ->and($crawler->filter('input[name="User[roles][]"]:checked'))->toHaveCount(0);
});

it('shows the password field on the edit page to an administrator', function (): void {
    $user = createRoleFormTestUser($this->em, [RoleEnum::User->value]);
    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $user->id->toString()));

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('input[name="User[plainPassword]"]'))->toHaveCount(1);
});

it('lists administrator and manager once each for a user with both roles', function (): void {
    $user = createRoleFormTestUser($this->em, [RoleEnum::Admin->value, RoleEnum::Manager->value]);
    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization?query=%s', urlencode($user->lastName)));
    $cells = $crawler->filter('td[data-column="roles"]');
    $labels = array_map('trim', explode(',', $cells->text()));
    sort($labels);

    expect($cells)->toHaveCount(1)
        ->and($labels)->toBe(['Administrator', 'Manager', 'User'])
        ->and($cells->text())->not->toContain('ROLE_');
});

it('keeps only the user role when a legacy typo role was the only stored role', function (): void {
    $user = createRoleFormTestUser($this->em, ['ROLE_MANGER']);
    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $user->id->toString()));

    expect($crawler->filter('input[name="User[roles][]"]:checked'))->toHaveCount(0);

    $form = $crawler->filter('button.action-saveAndReturn')->form();
    $this->client->submit($form);

    $this->em->clear();
    $reloaded = $this->em->find(User::class, $user->id);

    expect($this->client->getResponse()->getStatusCode())->toBe(302)
        ->and($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->roles)->toBe([RoleEnum::User->value]);
});

it('hides roles and password from managers on the edit page', function (): void {
    $user = createRoleFormTestUser($this->em, [RoleEnum::User->value]);
    $manager = $this->em->getRepository(User::class)->findOneBy(['email' => 'manager@whoisooo.app']);
    $this->client->loginUser($manager);

    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $user->id->toString()));

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('input[name="User[roles][]"]'))->toHaveCount(0)
        ->and($crawler->filter('input[name="User[plainPassword]"]'))->toHaveCount(0);
});

it('ignores a role forged by a manager', function (): void {
    $user = createRoleFormTestUser($this->em, [RoleEnum::User->value]);
    $originalPassword = $user->password;
    $manager = $this->em->getRepository(User::class)->findOneBy(['email' => 'manager@whoisooo.app']);
    $this->client->loginUser($manager);

    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $user->id->toString()));
    $form = $crawler->filter('button.action-saveAndReturn')->form();
    $values = $form->getPhpValues();
    $values['User']['roles'] = [RoleEnum::Admin->value];
    $values['User']['plainPassword'] = 'forged-password';
    $this->client->request($form->getMethod(), $form->getUri(), $values, $form->getPhpFiles());

    $this->em->clear();
    $reloaded = $this->em->find(User::class, $user->id);

    expect($this->client->getResponse()->isRedirection())->toBeTrue()
        ->and($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->roles)->toBe([RoleEnum::User->value])
        ->and($reloaded->password)->toBe($originalPassword);
});

it('ignores a forged admin role when a manager edits their own record', function (): void {
    $manager = createRoleFormTestUser($this->em, [RoleEnum::Manager->value]);
    $originalPassword = $manager->password;
    $this->client->loginUser($manager);

    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $manager->id->toString()));

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('input[name="User[roles][]"]'))->toHaveCount(0)
        ->and($crawler->filter('input[name="User[plainPassword]"]'))->toHaveCount(0);

    $form = $crawler->filter('button.action-saveAndReturn')->form();
    $values = $form->getPhpValues();
    $values['User']['roles'] = [RoleEnum::Admin->value];
    $values['User']['plainPassword'] = 'forged-password';
    $this->client->request($form->getMethod(), $form->getUri(), $values, $form->getPhpFiles());

    $this->em->clear();
    $reloaded = $this->em->find(User::class, $manager->id);

    expect($this->client->getResponse()->isRedirection())->toBeTrue()
        ->and($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->roles)->not->toContain(RoleEnum::Admin->value)
        ->and($reloaded->roles)->toEqualCanonicalizing([RoleEnum::Manager->value, RoleEnum::User->value])
        ->and($reloaded->password)->toBe($originalPassword);
});

it('shows the roles checkboxes and password field to a user with both admin and manager roles', function (): void {
    $adminAndManager = createRoleFormTestUser($this->em, [RoleEnum::Admin->value, RoleEnum::Manager->value]);
    $target = createRoleFormTestUser($this->em, [RoleEnum::User->value]);
    $this->client->loginUser($adminAndManager);

    $crawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $target->id->toString()));

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($crawler->filter('input[type=checkbox][name="User[roles][]"]')->extract(['value']))
        ->toBe(['ROLE_ADMIN', 'ROLE_MANAGER'])
        ->and($crawler->filter('input[name="User[plainPassword]"]'))->toHaveCount(1);
});

it('shows the security tab to an administrator but not to a manager on the edit page', function (): void {
    $target = createRoleFormTestUser($this->em, [RoleEnum::User->value]);
    $securityTab = 'a[href="#tab-security"], #tab-security';

    $adminCrawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $target->id->toString()));

    $manager = createRoleFormTestUser($this->em, [RoleEnum::Manager->value]);
    $this->client->loginUser($manager);
    $managerCrawler = $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $target->id->toString()));

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($managerCrawler->filter('#tab-employee-of-record')->count())->toBe(1)
        ->and($managerCrawler->filter($securityTab))->toHaveCount(0)
        ->and($adminCrawler->filter($securityTab))->toHaveCount(2);
});

it('denies the edit page to a user with only the user role', function (): void {
    $target = createRoleFormTestUser($this->em, [RoleEnum::User->value]);
    $viewer = createRoleFormTestUser($this->em, [RoleEnum::User->value]);
    $this->client->loginUser($viewer);

    $this->client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $target->id->toString()));

    expect($this->client->getResponse()->getStatusCode())->toBe(403);
});
