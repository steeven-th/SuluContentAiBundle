<?php

declare(strict_types=1);

namespace ItechWorld\SuluContentAiBundle\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;

/**
 * Admin integration for the AI assistant.
 *
 * Adds an "AI Assistant" button to the toolbar of the page and article edit
 * forms. The button is a form toolbar action (registered JS-side under the same
 * key) that opens an overlay hosting the chat panel — no host template change
 * required.
 */
final class ContentAiAdmin extends Admin
{
    /**
     * Toolbar action key — must match formToolbarActionRegistry.add() JS-side.
     */
    public const ASSISTANT_TOOLBAR_ACTION = 'iw_sulu_content_ai.assistant';

    /**
     * Toolbar action key for the "Generate SEO tags" button (SEO tab).
     */
    public const GENERATE_SEO_TOOLBAR_ACTION = 'iw_sulu_content_ai.generate_seo';

    /**
     * Toolbar action key for the "Translate media metadata" button (media form).
     */
    public const TRANSLATE_MEDIA_TOOLBAR_ACTION = 'iw_sulu_content_ai.translate_media';

    /**
     * Toolbar action key for the "Generate media metadata" button (media form, vision).
     */
    public const GENERATE_MEDIA_TOOLBAR_ACTION = 'iw_sulu_content_ai.generate_media';

    /**
     * Media edit form (details tab) that carries the metadata fields.
     */
    private const MEDIA_DETAILS_VIEW = 'sulu_media.form.details';

    public const SECURITY_CONTEXT = 'sulu.iw_sulu_content_ai.assistant';

    /**
     * Run after the core Sulu admins (priority 0) so the page/article/media views
     * already exist in the collection when we append our toolbar actions.
     */
    public static function getPriority(): int
    {
        return -10;
    }

    /**
     * Append our toolbar actions to the relevant page/article form views: the
     * assistant chat on the "content" tab and the SEO generator on the "seo" tab.
     *
     * The view builders are mutated in place through setOption() (getView()
     * returns a freshly built View that would not persist a change).
     */
    public function configureViews(ViewCollection $viewCollection): void
    {
        foreach ($viewCollection->all() as $viewBuilder) {
            $name = $viewBuilder->getName();

            if ($this->isAssistantTarget($name)) {
                $this->appendToolbarAction($viewBuilder, self::ASSISTANT_TOOLBAR_ACTION);
            } elseif ($this->isSeoTarget($name)) {
                $this->appendToolbarAction($viewBuilder, self::GENERATE_SEO_TOOLBAR_ACTION);
            } elseif (self::MEDIA_DETAILS_VIEW === $name) {
                $this->appendToolbarAction($viewBuilder, self::GENERATE_MEDIA_TOOLBAR_ACTION);
                $this->appendToolbarAction($viewBuilder, self::TRANSLATE_MEDIA_TOOLBAR_ACTION);
            }
        }
    }

    /**
     * Append a toolbar action to a view builder, preserving existing ones.
     */
    private function appendToolbarAction(ViewBuilderInterface $viewBuilder, string $action): void
    {
        $toolbarActions = $viewBuilder->getView()->getOption('toolbarActions') ?? [];
        $toolbarActions[] = new ToolbarAction($action);
        $viewBuilder->setOption('toolbarActions', $toolbarActions);
    }

    /**
     * @return array<string, array<string, array<string, list<string>>>>
     */
    public function getSecurityContexts(): array
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'AI Assistant' => [
                    self::SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::EDIT,
                    ],
                ],
            ],
        ];
    }

    public function getConfigKey(): ?string
    {
        return 'iw_sulu_content_ai';
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): ?array
    {
        return [
            'enabled' => true,
        ];
    }

    /**
     * The "content" sub-view of the page and article edit forms carries the full
     * toolbar action set. Article views are suffixed by their group identifier
     * (news/events/…), so they are matched by pattern rather than hardcoded.
     */
    private function isAssistantTarget(string $viewName): bool
    {
        if ('sulu_page.page_edit_form.content' === $viewName) {
            return true;
        }

        return str_starts_with($viewName, 'sulu_article.article.edit_tabs_')
            && str_ends_with($viewName, '.content');
    }

    /**
     * The "seo" sub-view of the page and article edit forms. Article views are
     * suffixed by their group identifier, so they are matched by pattern.
     */
    private function isSeoTarget(string $viewName): bool
    {
        if ('sulu_page.page_edit_form.seo' === $viewName) {
            return true;
        }

        return str_starts_with($viewName, 'sulu_article.article.edit_tabs_')
            && str_ends_with($viewName, '.seo');
    }
}
