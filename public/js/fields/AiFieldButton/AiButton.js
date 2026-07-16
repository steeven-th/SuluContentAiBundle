// @flow
import React from 'react';
import {Icon} from 'sulu-admin-bundle/components';
import {translate} from 'sulu-admin-bundle/utils';

type Props = {
    disabled?: boolean,
    onClick: () => void,
};

/**
 * Small overlay button opening the per-field writing assistant.
 */
export default class AiButton extends React.Component<Props> {
    handleClick = (event: SyntheticEvent<HTMLButtonElement>) => {
        event.preventDefault();
        event.stopPropagation();
        this.props.onClick();
    };

    render() {
        return (
            <button
                className="ai-field-button"
                disabled={this.props.disabled}
                onClick={this.handleClick}
                title={translate('iw_sulu_content_ai.writing_assistant')}
                type="button"
            >
                <Icon name="su-magic" />
            </button>
        );
    }
}
