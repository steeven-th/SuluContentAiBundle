// @flow
import React from 'react';
import {action, observable} from 'mobx';
import {AbstractFormToolbarAction} from 'sulu-admin-bundle/views';
import {Overlay} from 'sulu-admin-bundle/components';
import {translate} from 'sulu-admin-bundle/utils';
import AiChatPanel from '../components/AiChatPanel/AiChatPanel';

/**
 * Form toolbar action that opens the AI assistant chat in an overlay.
 *
 * The current resource context (resourceKey / id / locale) is read from the
 * form store and handed to the chat panel so the conversation is scoped to the
 * edited page or article.
 */
export default class AiAssistantToolbarAction extends AbstractFormToolbarAction {
    @observable open: boolean = false;

    getToolbarItemConfig() {
        return {
            type: 'button',
            icon: 'su-magic',
            label: translate('iw_sulu_content_ai.assistant'),
            onClick: action(() => {
                this.open = true;
            }),
        };
    }

    getNode() {
        const {resourceFormStore} = this;
        const locale = resourceFormStore.locale ? resourceFormStore.locale.get() : undefined;

        return (
            <Overlay
                key="iw_sulu_content_ai.assistant-overlay"
                onClose={action(() => {
                    this.open = false;
                })}
                open={this.open}
                size="large"
                title={translate('iw_sulu_content_ai.assistant_title')}
            >
                <AiChatPanel
                    id={resourceFormStore.id}
                    locale={locale}
                    open={this.open}
                    resourceFormStore={resourceFormStore}
                    resourceKey={resourceFormStore.resourceKey}
                />
            </Overlay>
        );
    }
}
