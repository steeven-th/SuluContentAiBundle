<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Admin;

use ItechWorld\SuluContentAiBundle\Entity\AiExpert;
use ItechWorld\SuluContentAiBundle\Entity\AiProviderConfig;
use ItechWorld\SuluContentAiBundle\Entity\AiPrompt;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

/**
 * Admin integration for the AI assistant settings: CRUD screens (list/add/edit)
 * for the AI experts and the predefined prompts, plus their navigation items
 * under "Settings".
 */
final class AiSettingsAdmin extends Admin
{
    public const EXPERTS_SECURITY_CONTEXT = 'sulu.iw_sulu_content_ai.experts';
    public const PROMPTS_SECURITY_CONTEXT = 'sulu.iw_sulu_content_ai.prompts';

    /**
     * Container tab view holding the Experts and Prompts list tabs.
     */
    public const SETTINGS_TAB_VIEW = 'iw_sulu_content_ai.settings';

    public const EXPERTS_LIST_VIEW = 'iw_sulu_content_ai.experts_list';
    public const EXPERTS_ADD_FORM_VIEW = 'iw_sulu_content_ai.experts_add_form';
    public const EXPERTS_EDIT_FORM_VIEW = 'iw_sulu_content_ai.experts_edit_form';

    public const PROMPTS_LIST_VIEW = 'iw_sulu_content_ai.prompts_list';
    public const PROMPTS_ADD_FORM_VIEW = 'iw_sulu_content_ai.prompts_add_form';
    public const PROMPTS_EDIT_FORM_VIEW = 'iw_sulu_content_ai.prompts_edit_form';

    public const PROVIDERS_SECURITY_CONTEXT = 'sulu.iw_sulu_content_ai.providers';

    public const PROVIDERS_LIST_VIEW = 'iw_sulu_content_ai.providers_list';
    public const PROVIDERS_ADD_FORM_VIEW = 'iw_sulu_content_ai.providers_add_form';
    public const PROVIDERS_EDIT_FORM_VIEW = 'iw_sulu_content_ai.providers_edit_form';

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
    ) {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        // Single "AI Assistant" entry under Settings; the lists live as tabs.
        if (!$this->securityChecker->hasPermission(self::EXPERTS_SECURITY_CONTEXT, PermissionTypes::EDIT)
            && !$this->securityChecker->hasPermission(self::PROMPTS_SECURITY_CONTEXT, PermissionTypes::EDIT)
            && !$this->securityChecker->hasPermission(self::PROVIDERS_SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            return;
        }

        $item = new NavigationItem('iw_sulu_content_ai.ai_assistant');
        $item->setPosition(40);
        $item->setView(self::SETTINGS_TAB_VIEW);

        $navigationItemCollection->get(Admin::SETTINGS_NAVIGATION_ITEM)->addChild($item);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        $hasExperts = $this->securityChecker->hasPermission(self::EXPERTS_SECURITY_CONTEXT, PermissionTypes::EDIT);
        $hasPrompts = $this->securityChecker->hasPermission(self::PROMPTS_SECURITY_CONTEXT, PermissionTypes::EDIT);
        $hasProviders = $this->securityChecker->hasPermission(self::PROVIDERS_SECURITY_CONTEXT, PermissionTypes::EDIT);

        if (!$hasExperts && !$hasPrompts && !$hasProviders) {
            return;
        }

        // Container tab view "AI Assistant" (redirects to its first tab).
        $viewCollection->add(
            $this->viewBuilderFactory->createTabViewBuilder(self::SETTINGS_TAB_VIEW, '/ai-assistant'),
        );

        if ($hasExperts) {
            $this->configureEntityViews(
                $viewCollection,
                AiExpert::RESOURCE_KEY,
                AiExpert::LIST_KEY,
                AiExpert::FORM_KEY,
                self::EXPERTS_LIST_VIEW,
                self::EXPERTS_ADD_FORM_VIEW,
                self::EXPERTS_EDIT_FORM_VIEW,
                '/experts',
                '/ai-assistant/experts',
                'iw_sulu_content_ai.experts',
                self::EXPERTS_SECURITY_CONTEXT,
                10,
            );
        }

        if ($hasPrompts) {
            $this->configureEntityViews(
                $viewCollection,
                AiPrompt::RESOURCE_KEY,
                AiPrompt::LIST_KEY,
                AiPrompt::FORM_KEY,
                self::PROMPTS_LIST_VIEW,
                self::PROMPTS_ADD_FORM_VIEW,
                self::PROMPTS_EDIT_FORM_VIEW,
                '/prompts',
                '/ai-assistant/prompts',
                'iw_sulu_content_ai.prompts',
                self::PROMPTS_SECURITY_CONTEXT,
                20,
            );
        }

        if ($hasProviders) {
            $this->configureEntityViews(
                $viewCollection,
                AiProviderConfig::RESOURCE_KEY,
                AiProviderConfig::LIST_KEY,
                AiProviderConfig::FORM_KEY,
                self::PROVIDERS_LIST_VIEW,
                self::PROVIDERS_ADD_FORM_VIEW,
                self::PROVIDERS_EDIT_FORM_VIEW,
                '/providers',
                '/ai-assistant/providers',
                'iw_sulu_content_ai.providers',
                self::PROVIDERS_SECURITY_CONTEXT,
                30,
                [],
                ['iw_sulu_content_ai.activate_provider'],
            );
        }
    }

    /**
     * Register one entity's views: a list rendered as a tab of the container view,
     * plus its (full-screen) add and edit forms.
     */
    private function configureEntityViews(
        ViewCollection $viewCollection,
        string $resourceKey,
        string $listKey,
        string $formKey,
        string $listView,
        string $addView,
        string $editView,
        string $tabPath,
        string $formPath,
        string $title,
        string $securityContext,
        int $tabOrder,
        array $extraFormActions = [],
        array $extraListActions = [],
    ): void {
        $formToolbarActions = [new ToolbarAction('sulu_admin.save')];
        foreach ($extraFormActions as $actionKey) {
            $formToolbarActions[] = new ToolbarAction($actionKey);
        }
        $listToolbarActions = [new ToolbarAction('sulu_admin.add')];

        if ($this->securityChecker->hasPermission($securityContext, PermissionTypes::DELETE)) {
            $formToolbarActions[] = new ToolbarAction('sulu_admin.delete');
            $listToolbarActions[] = new ToolbarAction('sulu_admin.delete');
        }

        foreach ($extraListActions as $actionKey) {
            $listToolbarActions[] = new ToolbarAction($actionKey);
        }

        // List as a tab of the container view.
        $viewCollection->add(
            $this->viewBuilderFactory->createListViewBuilder($listView, $tabPath)
                ->setResourceKey($resourceKey)
                ->setListKey($listKey)
                ->setTabTitle($title)
                ->setTabOrder($tabOrder)
                ->addListAdapters(['table'])
                ->setAddView($addView)
                ->setEditView($editView)
                ->addToolbarActions($listToolbarActions)
                ->setParent(self::SETTINGS_TAB_VIEW),
        );

        // Add form (full screen), returns to the list tab.
        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder($addView, $formPath.'/add')
                ->setResourceKey($resourceKey)
                ->setBackView($listView),
        );
        $viewCollection->add(
            $this->viewBuilderFactory->createFormViewBuilder($addView.'.details', '/details')
                ->setResourceKey($resourceKey)
                ->setFormKey($formKey)
                ->setTabTitle('sulu_admin.details')
                ->setEditView($editView)
                ->addToolbarActions($formToolbarActions)
                ->setParent($addView),
        );

        // Edit form (full screen).
        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder($editView, $formPath.'/:id')
                ->setResourceKey($resourceKey)
                ->setBackView($listView)
                ->setTitleProperty('name'),
        );
        $viewCollection->add(
            $this->viewBuilderFactory->createFormViewBuilder($editView.'.details', '/details')
                ->setResourceKey($resourceKey)
                ->setFormKey($formKey)
                ->setTabTitle('sulu_admin.details')
                ->addToolbarActions($formToolbarActions)
                ->setParent($editView),
        );
    }

    /**
     * @return array<string, array<string, array<string, list<string>>>>
     */
    public function getSecurityContexts(): array
    {
        // Grouped under "AI Assistant" (same group as the chat assistant context in
        // ContentAiAdmin), so the role permission matrix shows a clear section.
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'AI Assistant' => [
                    self::EXPERTS_SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::ADD,
                        PermissionTypes::EDIT,
                        PermissionTypes::DELETE,
                    ],
                    self::PROMPTS_SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::ADD,
                        PermissionTypes::EDIT,
                        PermissionTypes::DELETE,
                    ],
                    self::PROVIDERS_SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::ADD,
                        PermissionTypes::EDIT,
                        PermissionTypes::DELETE,
                    ],
                ],
            ],
        ];
    }
}
