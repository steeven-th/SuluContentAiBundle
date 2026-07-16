// @flow
import React from 'react';
import {action, observable} from 'mobx';
import {AbstractFormToolbarAction} from 'sulu-admin-bundle/views';
import {Checkbox, Dialog} from 'sulu-admin-bundle/components';
import {Requester} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';

/**
 * Media form toolbar action: generate the metadata (title, description) from the
 * image itself using a vision model.
 *
 * A confirmation dialog warns about overwriting and offers an "optimize existing
 * metadata" option (improve the current values instead of describing from scratch).
 */
export default class GenerateMediaMetadataToolbarAction extends AbstractFormToolbarAction {
    @observable showDialog: boolean = false;
    @observable optimize: boolean = false;
    @observable loading: boolean = false;

    getToolbarItemConfig() {
        const {id} = this.resourceFormStore;

        return {
            type: 'button',
            icon: 'su-magic',
            label: translate('iw_sulu_content_ai.generate_media'),
            disabled: !id,
            onClick: action(() => {
                this.showDialog = true;
            }),
        };
    }

    getNode() {
        return (
            <Dialog
                cancelText={translate('sulu_admin.cancel')}
                confirmLoading={this.loading}
                confirmText={translate('iw_sulu_content_ai.generate')}
                key="iw_sulu_content_ai.generate_media"
                onCancel={this.handleCancel}
                onConfirm={this.handleConfirm}
                open={this.showDialog}
                title={translate('iw_sulu_content_ai.generate_media')}
            >
                <p>{translate('iw_sulu_content_ai.generate_media_warning')}</p>
                <Checkbox checked={this.optimize} onChange={this.handleOptimizeChange}>
                    {translate('iw_sulu_content_ai.generate_media_optimize')}
                </Checkbox>
            </Dialog>
        );
    }

    @action handleOptimizeChange = (checked: boolean) => {
        this.optimize = checked;
    };

    @action handleCancel = () => {
        this.showDialog = false;
    };

    @action handleConfirm = () => {
        const store = this.resourceFormStore;
        const locale = store.locale ? store.locale.get() : undefined;

        if (!store.id || !locale) {
            return;
        }

        this.loading = true;

        Requester.post('/admin/api/content-ai/media/generate-metadata', {
            id: store.id,
            locale,
            optimize: this.optimize,
        })
            .then(action((response) => {
                const metadata = response && response.metadata;
                if (metadata) {
                    const values = {};
                    ['title', 'description'].forEach((key) => {
                        if ('string' === typeof metadata[key] && metadata[key]) {
                            values['/' + key] = metadata[key];
                        }
                    });
                    if (Object.keys(values).length > 0) {
                        store.changeMultiple(values);
                    }
                }

                this.loading = false;
                this.showDialog = false;
            }))
            .catch(action(() => {
                this.loading = false;
                this.showDialog = false;
            }));
    };
}
