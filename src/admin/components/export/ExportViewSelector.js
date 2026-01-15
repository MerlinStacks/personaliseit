/**
 * ExportViewSelector - View selection dropdown for multi-view exports
 */
import { SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Renders a view selector dropdown when multiple views exist
 * @param {Object} props - Component props
 * @param {Array} props.views - Available views
 * @param {string} props.currentViewId - Currently selected view ID
 * @param {Function} props.onViewChange - View change handler
 */
const ExportViewSelector = ({ views, currentViewId, onViewChange }) => {
    if (views.length <= 1) return null;

    return (
        <div className="personaliseit-export-view-selector">
            <SelectControl
                label={__('View', 'personaliseit')}
                value={currentViewId}
                options={views.map(v => ({
                    label: v.name || v.id,
                    value: v.id
                }))}
                onChange={onViewChange}
                __nextHasNoMarginBottom
            />
        </div>
    );
};

export default ExportViewSelector;
