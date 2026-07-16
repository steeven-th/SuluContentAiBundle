// @flow
import {action} from 'mobx';
import {AbstractListToolbarAction} from 'sulu-admin-bundle/views';
import {Requester} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';

/**
 * List toolbar action to activate the selected provider directly from the list
 * (the back-end deactivates the others, keeping a single active provider).
 */
export default class ActivateProviderToolbarAction extends AbstractListToolbarAction {
    getToolbarItemConfig() {
        return {
            type: 'button',
            icon: 'su-check-circle',
            label: translate('iw_sulu_content_ai.activate'),
            disabled: 1 !== this.listStore.selectionIds.length,
            onClick: this.handleClick,
        };
    }

    handleClick = action(() => {
        const id = this.listStore.selectionIds[0];
        if (!id) {
            return;
        }

        Requester.post('/admin/api/content-ai/providers/' + String(id) + '/activate', {})
            .then(() => {
                this.listStore.reload();
            })
            .catch(() => {});
    });
}
