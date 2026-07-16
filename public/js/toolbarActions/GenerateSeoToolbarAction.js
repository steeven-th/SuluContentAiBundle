// @flow
import {action, observable, toJS} from 'mobx';
import {AbstractFormToolbarAction} from 'sulu-admin-bundle/views';
import {Requester} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';

/**
 * Form toolbar action (SEO tab) that generates SEO metadata from the page
 * content and writes it back under /seo/*.
 *
 * The page content lives at the root of the shared form store even on the SEO
 * tab, so it is read directly and no extra request is needed to fetch it.
 */
export default class GenerateSeoToolbarAction extends AbstractFormToolbarAction {
    @observable loading: boolean = false;

    getToolbarItemConfig() {
        return {
            type: 'button',
            icon: 'su-magic',
            label: translate('iw_sulu_content_ai.generate_seo'),
            loading: this.loading,
            onClick: this.handleClick,
        };
    }

    handleClick = action(() => {
        const store = this.resourceFormStore;
        const locale = store.locale ? store.locale.get() : undefined;

        if (!locale || this.loading) {
            return;
        }

        this.loading = true;

        Requester.post('/admin/api/content-ai/seo', {
            locale,
            template: store.type,
            content: toJS(store.data) || {},
        })
            .then(action((response) => {
                const seo = response && response.seo;
                if (seo) {
                    // Only the three text fields; canonical/robots are left untouched.
                    store.changeMultiple({
                        '/seo/title': seo.title,
                        '/seo/description': seo.description,
                        '/seo/keywords': seo.keywords,
                    });
                }

                this.loading = false;
            }))
            .catch(action(() => {
                this.loading = false;
            }));
    });
}
