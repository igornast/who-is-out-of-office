<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\UserSlackIntegration;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SlackIntegrationCrudController extends AppAbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserSlackIntegration::class;
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
            TextField::new('slackMemberId'),
        ];
    }

    public function createEntity(string $entityFqcn): UserSlackIntegration
    {
        return new UserSlackIntegration(user: $this->getUser(), slackMemberId: '');
    }

    protected function getRedirectResponseAfterSave(AdminContext $context, string $action): RedirectResponse
    {
        $url = match ($action) {
            Action::NEW => $this->generateUrl('app_dashboard'),
            default => $this->container->get(AdminUrlGenerator::class)
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
