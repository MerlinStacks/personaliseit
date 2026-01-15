/**
 * MethodsTab - Personalisation methods settings tab
 * 
 * Enable/disable different personalisation methods.
 * 
 * @module MethodsTab
 */
import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Personalisation method definitions
 */
const METHODS = [
    {
        key: 'engraving',
        label: 'Engraving',
        description: __('Ideal for metal/wood. Restricts designs to monochrome or single texture. Often disables full color selection.', 'personaliseit')
    },
    {
        key: 'embroidery',
        label: 'Embroidery',
        description: __('Simulates stitched thread. May limit colors to specific thread palettes and adds texture effects.', 'personaliseit')
    },
    {
        key: 'dtf',
        label: 'DTF Printing',
        description: __('Direct Transfer Film. Enables full-color, high-detail prints suitable for most apparel.', 'personaliseit')
    },
    {
        key: 'uv',
        label: 'UV Printing',
        description: __('Direct UV curing. Enables full-color printing on hard surfaces (mugs, pens, cases).', 'personaliseit')
    },
    {
        key: 'sublimation',
        label: 'Sublimation',
        description: __('Dye transfer. best for white polyester or coated items. Allows full color and gradient designs.', 'personaliseit')
    },
];

/**
 * Methods settings tab
 * @param {Object} props - Component props
 * @param {Object} props.settings - Settings object
 * @param {Function} props.onChange - Settings change handler
 */
const MethodsTab = ({ settings, onChange }) => {
    return (
        <div className="tab-content animate-fade-in">
            <p className="tab-description">
                {__('Enable or disable personalisation methods available for products.', 'personaliseit')}
            </p>
            <div className="grid-options">
                {METHODS.map(method => (
                    <div key={method.key} className="option-card option-card--vertical">
                        <ToggleControl
                            label={<span className="option-card__label">{method.label}</span>}
                            checked={settings['personaliseit_enable_' + method.key]}
                            onChange={(val) => onChange('personaliseit_enable_' + method.key, val)}
                        />
                        <p className="option-card__description">
                            {method.description}
                        </p>
                    </div>
                ))}
            </div>
        </div>
    );
};

export default MethodsTab;
