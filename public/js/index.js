// @flow
import {formToolbarActionRegistry, listToolbarActionRegistry} from 'sulu-admin-bundle/views';
import {fieldRegistry} from 'sulu-admin-bundle/containers/Form';
import initializer from 'sulu-admin-bundle/services/initializer';
import AiAssistantToolbarAction from './toolbarActions/AiAssistantToolbarAction';
import GenerateSeoToolbarAction from './toolbarActions/GenerateSeoToolbarAction';
import TranslateMediaMetadataToolbarAction from './toolbarActions/TranslateMediaMetadataToolbarAction';
import GenerateMediaMetadataToolbarAction from './toolbarActions/GenerateMediaMetadataToolbarAction';
import ActivateProviderToolbarAction from './toolbarActions/ActivateProviderToolbarAction';
import withAiButton from './fields/AiFieldButton/withAiButton';
import AiModelSelect from './fields/AiProvider/AiModelSelect';
import './fields/AiFieldButton/ai-field.css';

// The keys must match the PHP-side ToolbarAction keys (ContentAiAdmin::*_TOOLBAR_ACTION).
formToolbarActionRegistry.add('iw_sulu_content_ai.assistant', AiAssistantToolbarAction);
formToolbarActionRegistry.add('iw_sulu_content_ai.generate_seo', GenerateSeoToolbarAction);
formToolbarActionRegistry.add('iw_sulu_content_ai.translate_media', TranslateMediaMetadataToolbarAction);
formToolbarActionRegistry.add('iw_sulu_content_ai.generate_media', GenerateMediaMetadataToolbarAction);

// List toolbar action to activate a provider from the list.
listToolbarActionRegistry.add('iw_sulu_content_ai.activate_provider', ActivateProviderToolbarAction);

// Custom field type for the provider form: model selects populated live from the
// provider API (GET .../models).
fieldRegistry.add('iw_ai_model_select', AiModelSelect);

// Overlay a per-field AI button on the text field types. The registry is
// pre-filled by Sulu, so we replace the entries directly (fieldRegistry.add
// throws on an existing key) once Sulu has initialized it.
initializer.addUpdateConfigHook('sulu_admin', (config, initialized) => {
    if (initialized) {
        return;
    }

    ['text_line', 'text_area'].forEach((fieldType) => {
        if (fieldRegistry.has(fieldType)) {
            fieldRegistry.fields[fieldType] = withAiButton(fieldRegistry.get(fieldType), false);
        }
    });

    ['text_editor'].forEach((fieldType) => {
        if (fieldRegistry.has(fieldType)) {
            fieldRegistry.fields[fieldType] = withAiButton(fieldRegistry.get(fieldType), true);
        }
    });
});
