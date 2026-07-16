// @flow
import React from 'react';
import {action, observable, toJS} from 'mobx';
import {observer} from 'mobx-react';
import {Button} from 'sulu-admin-bundle/components';
import {Requester} from 'sulu-admin-bundle/services';
import {translate} from 'sulu-admin-bundle/utils';

type Props = {
    id: ?(string | number),
    locale: ?string,
    open?: boolean,
    resourceFormStore: Object,
    resourceKey: ?string,
};

type ChatMessage = {
    content: string,
    role: 'assistant' | 'user',
};

// Inline styles keep the panel self-contained (no SCSS build dependency).
const styles = {
    container: {display: 'flex', flexDirection: 'column', height: '60vh', minWidth: '640px'},
    header: {display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 16px', borderBottom: '1px solid #eee'},
    headerTitle: {fontWeight: 'bold'},
    messages: {flex: 1, overflowY: 'auto', padding: '16px', display: 'flex', flexDirection: 'column', gap: '12px'},
    empty: {color: '#999', textAlign: 'center', margin: 'auto'},
    userMessage: {alignSelf: 'flex-end', background: '#e6f0ff', borderRadius: '8px', padding: '8px 12px', maxWidth: '80%', whiteSpace: 'pre-wrap'},
    assistantMessage: {alignSelf: 'flex-start', background: '#f2f2f2', borderRadius: '8px', padding: '8px 12px', maxWidth: '80%', whiteSpace: 'pre-wrap'},
    thinking: {alignSelf: 'flex-start', color: '#999', fontStyle: 'italic'},
    error: {color: '#cc0000'},
    capabilities: {display: 'flex', flexDirection: 'column', gap: '4px', padding: '8px 16px', borderTop: '1px solid #eee'},
    capability: {display: 'flex', alignItems: 'center', gap: '8px', fontSize: '13px', color: '#333'},
    warning: {fontSize: '12px', color: '#b8860b'},
    inputRow: {display: 'flex', gap: '12px', padding: '12px 16px 16px', alignItems: 'flex-end'},
    textarea: {flex: 1, resize: 'vertical', padding: '8px', borderRadius: '4px', border: '1px solid #ccc', fontFamily: 'inherit', fontSize: '14px'},
};

// Minimal, XSS-safe Markdown rendering: the raw text is HTML-escaped FIRST, then
// a small set of Markdown markers is converted to controlled tags.
function escapeHtml(text: string): string {
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function renderMarkdown(text: string): string {
    let html = escapeHtml(text);
    html = html.replace(/^\s*#{1,6}\s+(.*)$/gm, '<strong>$1</strong>');
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/(^|[^*])\*([^*\n]+?)\*/g, '$1<em>$2</em>');
    html = html.replace(/`(.+?)`/g, '<code>$1</code>');
    html = html.replace(/^\s*[-*]\s+(.*)$/gm, '• $1');
    html = html.replace(/\n/g, '<br />');
    return html;
}

@observer
export default class AiChatPanel extends React.Component<Props> {
    @observable messages: Array<ChatMessage> = [];
    @observable value: string = '';
    @observable loading: boolean = false;
    @observable error: ?string = null;
    @observable canCreateContent: boolean = true;
    @observable canModifyContent: boolean = false;
    // Off by default: the editor usually sets the page title by hand before
    // calling the assistant and does not want it overwritten.
    @observable modifyTitle: boolean = false;

    componentDidMount() {
        // Load the stored conversation when the panel opens (or is mounted open).
        if (false !== this.props.open) {
            this.loadHistory();
        }
    }

    componentDidUpdate(prevProps: Props) {
        // Reload when the overlay transitions from closed to open (panel kept mounted).
        if (!prevProps.open && this.props.open) {
            this.loadHistory();
        }
    }

    @action loadHistory = () => {
        const {id, locale, resourceKey} = this.props;
        if (!resourceKey || !id || !locale) {
            return;
        }

        const query = 'resourceKey=' + encodeURIComponent(String(resourceKey))
            + '&id=' + encodeURIComponent(String(id))
            + '&locale=' + encodeURIComponent(String(locale));

        Requester.get('/admin/api/content-ai/history?' + query)
            .then(action((response) => {
                if (response && Array.isArray(response.messages)) {
                    this.messages = response.messages
                        .filter((message) => 'user' === message.role || 'assistant' === message.role)
                        .map((message) => ({role: message.role, content: message.content}));
                }
            }))
            .catch(() => {});
    };

    @action handleChange = (event: SyntheticInputEvent<HTMLTextAreaElement>) => {
        this.value = event.currentTarget.value;
    };

    @action handleToggleCreate = (event: SyntheticInputEvent<HTMLInputElement>) => {
        this.canCreateContent = event.currentTarget.checked;
    };

    @action handleToggleModify = (event: SyntheticInputEvent<HTMLInputElement>) => {
        this.canModifyContent = event.currentTarget.checked;
    };

    @action handleToggleModifyTitle = (event: SyntheticInputEvent<HTMLInputElement>) => {
        this.modifyTitle = event.currentTarget.checked;
    };

    handleKeyDown = (event: SyntheticKeyboardEvent<HTMLTextAreaElement>) => {
        if ('Enter' === event.key && (event.metaKey || event.ctrlKey)) {
            this.handleSend();
        }
    };

    @action handleClear = () => {
        const {id, locale, resourceKey} = this.props;
        this.messages = [];
        this.error = null;
        Requester.post('/admin/api/content-ai/clear', {resourceKey, id, locale}).catch(() => {});
    };

    @action handleSend = () => {
        const prompt = this.value.trim();
        if (!prompt || this.loading) {
            return;
        }

        const {id, locale, resourceKey} = this.props;
        const store = this.props.resourceFormStore;

        this.messages.push({role: 'user', content: prompt});
        this.value = '';
        this.error = null;
        this.loading = true;

        Requester.post('/admin/api/content-ai/chat', {
            resourceKey,
            id,
            locale,
            message: prompt,
            template: store.type,
            canCreateContent: this.canCreateContent,
            canModifyContent: this.canModifyContent,
            canModifyTitle: this.modifyTitle,
            currentContent: toJS(store.data) || {},
        })
            .then(action((response) => {
                this.messages.push({role: 'assistant', content: (response && response.message) || ''});

                if (response && response.content && 'object' === typeof response.content) {
                    this.applyContent(response.content, response.mode);
                }

                this.loading = false;
            }))
            .catch(action(() => {
                this.error = translate('iw_sulu_content_ai.error');
                this.loading = false;
            }));
    };

    // Apply the generated content to the form (mark it dirty). Block lists are
    // appended in "append" mode and replaced in "replace" mode; scalars are set.
    applyContent(content: Object, mode: ?string) {
        const store = this.props.resourceFormStore;
        const values = {};

        Object.keys(content).forEach((key) => {
            const value = content[key];
            const path = '/' + key;

            if (Array.isArray(value) && 'replace' !== mode) {
                const current = toJS(store.getValueByPath(path)) || [];
                values[path] = [...current, ...value];
            } else {
                values[path] = value;
            }
        });

        if (Object.keys(values).length > 0) {
            store.changeMultiple(values);
        }
    }

    render() {
        return (
            <div style={styles.container}>
                <div style={styles.header}>
                    <span style={styles.headerTitle}>{translate('iw_sulu_content_ai.assistant_title')}</span>
                    <Button onClick={this.handleClear} skin="link">
                        {translate('iw_sulu_content_ai.clear')}
                    </Button>
                </div>

                <div style={styles.messages}>
                    {0 === this.messages.length &&
                        <div style={styles.empty}>{translate('iw_sulu_content_ai.empty')}</div>
                    }
                    {this.messages.map((message, index) => (
                        'user' === message.role
                            ? (
                                <div key={index} style={styles.userMessage}>
                                    {message.content}
                                </div>
                            )
                            : (
                                <div
                                    key={index}
                                    dangerouslySetInnerHTML={{__html: renderMarkdown(message.content)}}
                                    style={styles.assistantMessage}
                                />
                            )
                    ))}
                    {this.loading &&
                        <div style={styles.thinking}>{translate('iw_sulu_content_ai.thinking')}</div>
                    }
                    {this.error &&
                        <div style={styles.error}>{this.error}</div>
                    }
                </div>

                <div style={styles.capabilities}>
                    <label style={styles.capability}>
                        <input checked={this.canCreateContent} onChange={this.handleToggleCreate} type="checkbox" />
                        {translate('iw_sulu_content_ai.can_create')}
                    </label>
                    <label style={styles.capability}>
                        <input checked={this.canModifyContent} onChange={this.handleToggleModify} type="checkbox" />
                        {translate('iw_sulu_content_ai.can_modify')}
                    </label>
                    {(this.canCreateContent || this.canModifyContent) &&
                        <label style={styles.capability}>
                            <input checked={this.modifyTitle} onChange={this.handleToggleModifyTitle} type="checkbox" />
                            {translate('iw_sulu_content_ai.modify_title')}
                        </label>
                    }
                    {this.canModifyContent &&
                        <div style={styles.warning}>{translate('iw_sulu_content_ai.modify_warning')}</div>
                    }
                </div>

                <div style={styles.inputRow}>
                    <textarea
                        onChange={this.handleChange}
                        onKeyDown={this.handleKeyDown}
                        placeholder={translate('iw_sulu_content_ai.placeholder')}
                        rows={3}
                        style={styles.textarea}
                        value={this.value}
                    />
                    <Button disabled={this.loading} onClick={this.handleSend} skin="primary">
                        {translate('iw_sulu_content_ai.send')}
                    </Button>
                </div>
            </div>
        );
    }
}
