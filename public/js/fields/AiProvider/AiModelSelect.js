// @flow
import React from 'react';
import {action, observable} from 'mobx';
import {observer} from 'mobx-react';
import {SingleSelect} from 'sulu-admin-bundle/components';
import {Requester} from 'sulu-admin-bundle/services';

/**
 * Provider "model" field: a select populated live from the provider API
 * (GET .../models). When the provider is not yet reachable (e.g. on creation,
 * before the key is saved), it falls back to a free text input.
 */
@observer
export default class AiModelSelect extends React.Component<*> {
    @observable models: Array<string> = [];
    @observable loaded: boolean = false;

    componentDidMount() {
        const {formInspector} = this.props;
        const id = formInspector && formInspector.id;

        if (!id) {
            this.markLoaded();

            return;
        }

        Requester.get('/admin/api/content-ai/providers/' + String(id) + '/models')
            .then(action((response) => {
                this.models = (response && response.models) || [];
                this.loaded = true;
            }))
            .catch(action(() => {
                this.loaded = true;
            }));
    }

    @action markLoaded = () => {
        this.loaded = true;
    };

    handleChange = (value: ?string) => {
        const {onChange, onFinish} = this.props;
        onChange(value);
        if (onFinish) {
            onFinish();
        }
    };

    handleInput = (event: SyntheticInputEvent<HTMLInputElement>) => {
        this.props.onChange(event.currentTarget.value);
    };

    handleBlur = () => {
        if (this.props.onFinish) {
            this.props.onFinish();
        }
    };

    render() {
        const {value, disabled} = this.props;

        // No models available (creating, or provider unreachable) → free text input.
        if (this.loaded && 0 === this.models.length) {
            return (
                <input
                    disabled={disabled}
                    onBlur={this.handleBlur}
                    onChange={this.handleInput}
                    placeholder="mistral-small-latest"
                    style={{width: '100%', boxSizing: 'border-box', padding: '8px', border: '1px solid #ccc', borderRadius: '4px'}}
                    type="text"
                    value={value || ''}
                />
            );
        }

        return (
            <SingleSelect disabled={disabled} onChange={this.handleChange} value={value || undefined}>
                <SingleSelect.Option value={undefined}>—</SingleSelect.Option>
                {value && !this.models.includes(value) &&
                    <SingleSelect.Option value={value}>{value}</SingleSelect.Option>
                }
                {this.models.map((model) => (
                    <SingleSelect.Option key={model} value={model}>{model}</SingleSelect.Option>
                ))}
            </SingleSelect>
        );
    }
}
