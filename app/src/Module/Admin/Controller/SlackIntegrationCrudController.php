<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\UserSlackIntegration;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @extends AppAbstractCrudController<UserSlackIntegration>
 */
#[AdminRoute(path: '/integrations/slack', name: 'app_integrations_slack')]
class SlackIntegrationCrudController extends AppAbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return UserSlackIntegration::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_NEW, 'crud.slack_integration.new.title')
            ->setPageTitle(Crud::PAGE_EDIT, 'crud.slack_integration.edit.title')
            ->setSearchFields(null);
    }

    public function configureActions(Actions $actions): Actions
    {

        return $actions
            ->setPermission(Action::EDIT, new Expression('object.user == user'))
            ->disable(Action::INDEX, Action::DELETE, Action::DETAIL, Action::TYPE_BATCH, Action::SAVE_AND_CONTINUE, Action::SAVE_AND_ADD_ANOTHER, Action::BATCH_DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('slackMemberId')
                ->setLabel('crud.slack_integration.field.slack_member_id')
                ->setHelp('crud.slack_integration.field.slack_member_id_help'),
        ];
    }

    public function createEntity(string $entityFqcn): UserSlackIntegration
    {
        return new UserSlackIntegration(user: $this->getUser(), slackMemberId: '');
    }

    /**
     * @param AdminContext<UserSlackIntegration> $context
     */
    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $url = match ($action) {
            Action::NEW => $this->generateUrl('app_dashboard'),
            default => $this->adminUrlGenerator
                ->setAction(Action::EDIT)
                ->setEntityId($context->getEntity()->getPrimaryKeyValue()->id)
                ->generateUrl(),
        };

        $this->addFlash(
            type: 'success',
            message: Action::NEW === $action ? 'flash.crud.slack-integration.success.new' : 'flash.crud.slack-integration.success.edit',
        );

        return $this->redirect($url);
    }
}
