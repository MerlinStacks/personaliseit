/**
 * AiTab - AI integration settings tab
 * 
 * OpenRouter API configuration and AI feature controls.
 * 
 * @module AiTab
 */
import { ToggleControl, TextControl, Button, ComboboxControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * AI settings tab
 * @param {Object} props - Component props
 * @param {Object} props.settings - Settings object
 * @param {Function} props.onChange - Settings change handler
 * @param {Array} props.models - Available AI models
 * @param {boolean} props.isLoadingModels - Models loading state
 * @param {Function} props.onFetchModels - Fetch models callback
 * @param {string} props.filterValue - Model filter value
 * @param {Function} props.onFilterChange - Filter change callback
 */
const AiTab = ({
    settings,
    onChange,
    models,
    isLoadingModels,
    onFetchModels,
    filterValue,
    onFilterChange
}) => {
    // Ensure current model is in the options list
    const filteredModels = [...models];
    if (settings.personaliseit_ai_model && !models.find(m => m.value === settings.personaliseit_ai_model)) {
        filteredModels.unshift({
            value: settings.personaliseit_ai_model,
            label: settings.personaliseit_ai_model
        });
    }

    return (
        <div className="tab-content animate-fade-in">
            <div className="section-block">
                <h2 className="section-title">{__('OpenRouter Integration', 'personaliseit')}</h2>
                <p className="description section-description">
                    {__('Enter your OpenRouter API Key to enable AI features. These features act on behalf of your account.', 'personaliseit')}
                </p>
                <TextControl
                    label={__('OpenRouter API Key', 'personaliseit')}
                    type="password"
                    value={settings.personaliseit_openrouter_api_key}
                    onChange={(val) => onChange('personaliseit_openrouter_api_key', val)}
                    help={__('Get your key from openrouter.ai', 'personaliseit')}
                />

                <div className="control-group">
                    <label className="control-label">{__('AI Model', 'personaliseit')}</label>
                    <div className="model-selector">
                        <div className="model-selector__input">
                            <ComboboxControl
                                label=""
                                value={settings.personaliseit_ai_model}
                                onChange={(val) => onChange('personaliseit_ai_model', val)}
                                options={filteredModels.filter(opt =>
                                    opt.label.toLowerCase().includes(filterValue.toLowerCase())
                                )}
                                onFilterValueChange={onFilterChange}
                                allowReset={false}
                            />
                        </div>
                        <Button
                            isSecondary
                            onClick={onFetchModels}
                            isBusy={isLoadingModels}
                            disabled={!settings.personaliseit_openrouter_api_key}
                        >
                            {__('Refresh Models', 'personaliseit')}
                        </Button>
                    </div>
                    <p className="description">
                        {__('Search or enter a model ID (e.g., stabilityai/stable-diffusion-xl-base-1.0).', 'personaliseit')}
                    </p>
                </div>
            </div>

            <div className="section-block">
                <h2 className="section-title">{__('Feature Controls', 'personaliseit')}</h2>
                <ToggleControl
                    label={__('Enable Image Generation', 'personaliseit')}
                    checked={settings.personaliseit_enable_ai_generate}
                    onChange={(val) => onChange('personaliseit_enable_ai_generate', val)}
                    help={__('Allow users to generate images from scratch using prompts.', 'personaliseit')}
                />

                <div className="section-divider"></div>

                <ToggleControl
                    label={__('Enable Style Transfer', 'personaliseit')}
                    checked={settings.personaliseit_enable_ai_style}
                    onChange={(val) => onChange('personaliseit_enable_ai_style', val)}
                    help={__('Allow users to restyle uploaded images.', 'personaliseit')}
                />

                {settings.personaliseit_enable_ai_style && (
                    <div className="nested-settings nested-settings--bordered">
                        <h3 className="nested-settings__title">{__('Unified Style Prompt', 'personaliseit')}</h3>
                        <TextControl
                            label={__('Default Prompt Suffix', 'personaliseit')}
                            value={settings.personaliseit_ai_style_prompt}
                            onChange={(val) => onChange('personaliseit_ai_style_prompt', val)}
                            help={__('Used if no specific preset is selected. E.g. "Make it look like a cartoon"', 'personaliseit')}
                        />
                        <p className="description tip-text">
                            {__('Tip: Manage advanced Style Presets in the "Artist Styles" menu.', 'personaliseit')}
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
};

export default AiTab;
