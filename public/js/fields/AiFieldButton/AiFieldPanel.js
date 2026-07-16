// @flow
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {Button, Overlay, SingleSelect} from 'sulu-admin-bundle/components';
import {Requester} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';

type Props = {
    isHtml: boolean,
    locale: ?string,
    onClose: () => void,
    onInsert: (value: string) => void,
    open: boolean,
    value: ?string,
};

type Option = {id: number, name: string, prompt?: string};

// Inline styles keep the panel self-contained (no SCSS build dependency).
const styles = {
    label: {fontWeight: 'bold', fontSize: '13px', margin: '0 0 4px'},
    selected: {maxHeight: '120px', overflowY: 'auto', whiteSpace: 'pre-wrap', background: '#f7f7f7', border: '1px solid #eee', borderRadius: '4px', padding: '8px', fontSize: '13px', color: '#333'},
    result: {maxHeight: '200px', overflowY: 'auto', whiteSpace: 'pre-wrap', background: '#f2f0fb', border: '1px solid #e0d8f7', borderRadius: '4px', padding: '8px', fontSize: '13px', color: '#222'},
    selects: {display: 'flex', gap: '12px', margin: '12px 0 8px'},
    select: {flex: 1, minWidth: 0},
    textarea: {width: '100%', boxSizing: 'border-box', resize: 'vertical', padding: '8px', borderRadius: '4px', border: '1px solid #ccc', fontFamily: 'inherit', fontSize: '14px'},
    row: {display: 'flex', gap: '8px', alignItems: 'center', marginTop: '8px'},
    spacer: {flex: 1},
    error: {color: '#cc0000', marginTop: '8px'},
    section: {marginTop: '16px'},
    counter: {fontSize: '11px', color: '#999', textAlign: 'right', marginTop: '4px'},
};

/**
 * Stateless per-field writing assistant panel: no history, no page context.
 * Selected text + an instruction (optionally under an expert / a predefined
 * prompt) → a transformed value the editor can insert into the field.
 */
@observer
export default class AiFieldPanel extends React.Component<Props> {
    @observable instruction: string = '';
    @observable expertId: ?string = undefined;
    @observable promptId: ?string = undefined;
    @observable result: ?string = undefined;
    @observable loading: boolean = false;
    @observable error: ?string = null;
    @observable experts: Array<Option> = [];
    @observable prompts: Array<Option> = [];
    @observable optionsLoaded: boolean = false;

    componentDidUpdate(prevProps: Props) {
        if (!prevProps.open && this.props.open) {
            this.reset();
            this.loadOptions();
        }
    }

    @action reset = () => {
        this.instruction = '';
        this.expertId = undefined;
        this.promptId = undefined;
        this.result = undefined;
        this.error = null;
        this.loading = false;
    };

    @action loadOptions = () => {
        if (this.optionsLoaded) {
            return;
        }

        Requester.get('/admin/api/content-ai/field/options')
            .then(action((response) => {
                this.experts = (response && response.experts) || [];
                this.prompts = (response && response.prompts) || [];
                this.optionsLoaded = true;
            }))
            .catch(() => {});
    };

    @action handleExpertChange = (expertId: ?string) => {
        this.expertId = expertId;
    };

    @action handlePromptChange = (promptId: ?string) => {
        this.promptId = promptId;
        const prompt = this.prompts.find((item) => String(item.id) === String(promptId));
        if (prompt && prompt.prompt) {
            this.instruction = prompt.prompt;
        }
    };

    @action handleInstructionChange = (event: SyntheticInputEvent<HTMLTextAreaElement>) => {
        this.instruction = event.currentTarget.value;
    };

    @action handleSend = () => {
        const {value, locale, isHtml} = this.props;

        if (this.loading || !locale) {
            return;
        }

        this.loading = true;
        this.error = null;

        Requester.post('/admin/api/content-ai/field/transform', {
            value: value || '',
            instruction: this.instruction,
            locale,
            isHtml,
            expertId: this.expertId ? Number(this.expertId) : null,
        })
            .then(action((response) => {
                this.result = (response && response.result) || '';
                this.loading = false;
            }))
            .catch(action(() => {
                this.error = translate('iw_sulu_content_ai.error');
                this.loading = false;
            }));
    };

    handleInsert = () => {
        if (null !== this.result && undefined !== this.result) {
            this.props.onInsert(this.result);
        }
    };

    handleCopy = () => {
        if (this.result && navigator.clipboard) {
            navigator.clipboard.writeText(this.result).catch(() => {});
        }
    };

    // Strip tags for a readable preview (HTML fields); the raw value is inserted.
    preview(text: ?string): string {
        if (!text) {
            return '';
        }

        return this.props.isHtml ? text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : text;
    }

    // Character count of the visible text (HTML stripped), for the counters.
    charCount(text: ?string): number {
        return this.preview(text).length;
    }

    // Signed delta vs the original value (green if longer, red if shorter).
    renderDelta() {
        const delta = this.charCount(this.result) - this.charCount(this.props.value);
        if (0 === delta) {
            return null;
        }

        return (
            <span style={{color: delta > 0 ? '#2e7d32' : '#c62828', marginLeft: '4px'}}>
                {(delta > 0 ? '+' : '') + delta}
            </span>
        );
    }

    render() {
        const {onClose, open} = this.props;

        return (
            <Overlay
                confirmDisabled={!this.result}
                confirmText={translate('iw_sulu_content_ai.insert')}
                onClose={onClose}
                onConfirm={this.handleInsert}
                open={open}
                size="small"
                title={translate('iw_sulu_content_ai.writing_assistant')}
            >
                <div style={{padding: '24px'}}>
                    <p style={styles.label}>{translate('iw_sulu_content_ai.selected_text')}</p>
                    <div style={styles.selected}>{this.preview(this.props.value) || '—'}</div>
                    <div style={styles.counter}>
                        {this.charCount(this.props.value) + ' ' + translate('iw_sulu_content_ai.characters')}
                    </div>

                    <div style={styles.selects}>
                        <div style={styles.select}>
                            <SingleSelect onChange={this.handleExpertChange} value={this.expertId}>
                                <SingleSelect.Option value={undefined}>
                                    {translate('iw_sulu_content_ai.writing_assistant')}
                                </SingleSelect.Option>
                                {this.experts.map((expert) => (
                                    <SingleSelect.Option key={expert.id} value={String(expert.id)}>
                                        {expert.name}
                                    </SingleSelect.Option>
                                ))}
                            </SingleSelect>
                        </div>
                        <div style={styles.select}>
                            <SingleSelect onChange={this.handlePromptChange} value={this.promptId}>
                                <SingleSelect.Option value={undefined}>
                                    {translate('iw_sulu_content_ai.predefined_prompts')}
                                </SingleSelect.Option>
                                {this.prompts.map((prompt) => (
                                    <SingleSelect.Option key={prompt.id} value={String(prompt.id)}>
                                        {prompt.name}
                                    </SingleSelect.Option>
                                ))}
                            </SingleSelect>
                        </div>
                    </div>

                    <textarea
                        onChange={this.handleInstructionChange}
                        placeholder={translate('iw_sulu_content_ai.field_placeholder')}
                        rows={2}
                        style={styles.textarea}
                        value={this.instruction}
                    />

                    <div style={styles.row}>
                        <span style={styles.spacer} />
                        <Button disabled={this.loading} onClick={this.handleSend} skin="primary">
                            {this.loading ? translate('iw_sulu_content_ai.thinking') : translate('iw_sulu_content_ai.send')}
                        </Button>
                    </div>

                    {this.error &&
                        <div style={styles.error}>{this.error}</div>
                    }

                    {null !== this.result && undefined !== this.result &&
                        <div style={styles.section}>
                            <p style={styles.label}>{translate('iw_sulu_content_ai.result')}</p>
                            <div style={styles.result}>{this.preview(this.result) || '—'}</div>
                            <div style={styles.counter}>
                                {this.charCount(this.result) + ' ' + translate('iw_sulu_content_ai.characters')}
                                {this.renderDelta()}
                            </div>
                            <div style={styles.row}>
                                <Button onClick={this.handleSend} skin="link">
                                    {translate('iw_sulu_content_ai.regenerate')}
                                </Button>
                                <Button onClick={this.handleCopy} skin="link">
                                    {translate('iw_sulu_content_ai.copy')}
                                </Button>
                            </div>
                        </div>
                    }
                </div>
            </Overlay>
        );
    }
}
