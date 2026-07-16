// @flow
import React from 'react';
import {action, computed, observable} from 'mobx';
import {AbstractFormToolbarAction} from 'sulu-admin-bundle/views';
import {Dialog, SingleSelect} from 'sulu-admin-bundle/components';
import {Requester} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';

/**
 * Media form toolbar action: translate the media metadata (title, description,
 * copyright, credits) from a chosen source locale into the current locale.
 *
 * The current form is the target: the translated values are applied on the
 * shared form store (source → current-locale flow), so the editor reviews and
 * saves them like any other change.
 */
export default class TranslateMediaMetadataToolbarAction extends AbstractFormToolbarAction {
    @observable showDialog: boolean = false;
    @observable sourceLocale: ?string = undefined;
    @observable loading: boolean = false;

    @computed get otherLocales(): Array<string> {
        const current = this.resourceFormStore.locale ? this.resourceFormStore.locale.get() : undefined;

        return (this.locales || []).filter((locale) => locale !== current);
    }

    getToolbarItemConfig() {
        const {id} = this.resourceFormStore;

        return {
            type: 'button',
            icon: 'su-language',
            label: translate('iw_sulu_content_ai.translate_media'),
            disabled: !id || this.otherLocales.length === 0,
            onClick: action(() => {
                this.sourceLocale = this.otherLocales[0];
                this.showDialog = true;
            }),
        };
    }

    getNode() {
        return (
            <Dialog
                cancelText={translate('sulu_admin.cancel')}
                confirmDisabled={!this.sourceLocale}
                confirmLoading={this.loading}
                confirmText={translate('sulu_admin.ok')}
                key="iw_sulu_content_ai.translate_media"
                onCancel={this.handleCancel}
                onConfirm={this.handleConfirm}
                open={this.showDialog}
                title={translate('iw_sulu_content_ai.translate_media')}
            >
                <p>{translate('iw_sulu_content_ai.translate_media_source')}</p>
                <SingleSelect onChange={this.handleLocaleChange} value={this.sourceLocale}>
                    {this.otherLocales.map((locale) => (
                        <SingleSelect.Option key={locale} value={locale}>
                            {locale}
                        </SingleSelect.Option>
                    ))}
                </SingleSelect>
            </Dialog>
        );
    }

    @action handleLocaleChange = (sourceLocale: string) => {
        this.sourceLocale = sourceLocale;
    };

    @action handleCancel = () => {
        this.showDialog = false;
    };

    @action handleConfirm = () => {
        const store = this.resourceFormStore;
        const targetLocale = store.locale ? store.locale.get() : undefined;

        if (!this.sourceLocale || !targetLocale || !store.id) {
            return;
        }

        this.loading = true;

        Requester.post('/admin/api/content-ai/media/translate-metadata', {
            id: store.id,
            sourceLocale: this.sourceLocale,
            targetLocale,
        })
            .then(action((response) => {
                const metadata = response && response.metadata;
                if (metadata) {
                    const values = {};
                    ['title', 'description', 'copyright', 'credits'].forEach((key) => {
                        if ('string' === typeof metadata[key]) {
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
