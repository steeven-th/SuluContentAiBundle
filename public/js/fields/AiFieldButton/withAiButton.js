// @flow
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import AiButton from './AiButton';
import AiFieldPanel from './AiFieldPanel';

/**
 * Higher-order component that overlays a per-field AI button on a Sulu field
 * type and, on click, opens the stateless writing assistant panel. The wrapped
 * field type receives all its props unchanged.
 *
 * @param {ComponentType} WrappedComponent Original Sulu field type
 * @param {boolean} isHtml Whether the field holds HTML (text_editor)
 */
export default function withAiButton(WrappedComponent: React$ComponentType<*>, isHtml: boolean = false) {
    @observer
    class AiFieldDecorator extends React.Component<*> {
        @observable open: boolean = false;

        @action handleOpen = () => {
            this.open = true;
        };

        @action handleClose = () => {
            this.open = false;
        };

        handleInsert = (value: string) => {
            const {onChange, onFinish} = this.props;
            onChange(value);
            if (onFinish) {
                onFinish();
            }
            this.handleClose();
        };

        getLocale(): ?string {
            const {formInspector} = this.props;
            if (!formInspector || !formInspector.locale) {
                return null;
            }

            return 'function' === typeof formInspector.locale.get
                ? formInspector.locale.get()
                : formInspector.locale;
        }

        render() {
            const {value} = this.props;
            const locale = this.getLocale();

            // Only decorate localized (editorial) content — like the translator button.
            // Global settings forms (experts, providers, …) have no locale: leave the
            // original field untouched there.
            if (!locale) {
                return <WrappedComponent {...this.props} />;
            }

            const wrapperClass = 'ai-field-wrapper' + (isHtml ? ' ai-field-wrapper--editor' : '');

            return (
                <div className={wrapperClass}>
                    <WrappedComponent {...this.props} />
                    <AiButton onClick={this.handleOpen} />
                    <AiFieldPanel
                        isHtml={isHtml}
                        locale={locale}
                        onClose={this.handleClose}
                        onInsert={this.handleInsert}
                        open={this.open}
                        value={'string' === typeof value ? value : ''}
                    />
                </div>
            );
        }
    }

    AiFieldDecorator.displayName = `withAiButton(${WrappedComponent.displayName || WrappedComponent.name || 'Component'})`;

    return AiFieldDecorator;
}
