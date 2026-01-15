/**
 * GeneralTab - General settings tab content
 * 
 * Canvas defaults, upload restrictions, and display options.
 * 
 * @module GeneralTab
 */
import { ToggleControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * General settings tab
 * @param {Object} props - Component props
 * @param {Object} props.settings - Settings object
 * @param {Function} props.onChange - Settings change handler
 */
const GeneralTab = ({ settings, onChange }) => {
    return (
        <div className="tab-content animate-fade-in">
            <div className="section-block">
                <h2 className="section-title">{__('Canvas Defaults', 'personaliseit')}</h2>
                <div className="form-row two-col">
                    <TextControl
                        label={__('Default Width (px)', 'personaliseit')}
                        type="number"
                        value={settings.personaliseit_canvas_width}
                        onChange={(val) => onChange('personaliseit_canvas_width', parseInt(val))}
                    />
                    <TextControl
                        label={__('Default Height (px)', 'personaliseit')}
                        type="number"
                        value={settings.personaliseit_canvas_height}
                        onChange={(val) => onChange('personaliseit_canvas_height', parseInt(val))}
                    />
                </div>
            </div>

            <div className="section-block">
                <h2 className="section-title">{__('Upload Restrictions', 'personaliseit')}</h2>
                <TextControl
                    label={__('Max Image Upload Size (MB)', 'personaliseit')}
                    help={__('Limit the size of images customers can upload.', 'personaliseit')}
                    type="number"
                    value={settings.personaliseit_max_upload_size}
                    onChange={(val) => onChange('personaliseit_max_upload_size', parseInt(val))}
                />
            </div>

            <div className="section-block">
                <h2 className="section-title">{__('Display Options', 'personaliseit')}</h2>
                <div className="toggle-group">
                    <ToggleControl
                        label={__('Show Personalisation Cost', 'personaliseit')}
                        help={__('Display the additional cost for personalisation on the frontend.', 'personaliseit')}
                        checked={settings.personaliseit_show_cost}
                        onChange={(val) => onChange('personaliseit_show_cost', val)}
                    />
                </div>

                <div className="control-group">
                    <label className="control-label">{__('Input Label Position', 'personaliseit')}</label>
                    <select
                        value={settings.personaliseit_label_position}
                        onChange={(e) => onChange('personaliseit_label_position', e.target.value)}
                        className="regular-select"
                    >
                        <option value="above">{__('Above Control', 'personaliseit')}</option>
                        <option value="below">{__('Below Control', 'personaliseit')}</option>
                        <option value="left">{__('Left of Control', 'personaliseit')}</option>
                        <option value="right">{__('Right of Control', 'personaliseit')}</option>
                    </select>
                    <p className="description">
                        {__('Choose how labels are positioned relative to the input fields on the frontend.', 'personaliseit')}
                    </p>
                </div>
            </div>
        </div>
    );
};

export default GeneralTab;
