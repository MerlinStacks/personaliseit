/**
 * DownloadsTab - File downloads settings tab
 * 
 * Configure which file formats are available for download.
 * 
 * @module DownloadsTab
 */
import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Download format definitions
 */
const FORMATS = [
    { key: 'pdf_download', label: 'PDF' },
    { key: 'svg_download', label: 'SVG' },
    { key: 'jpg_download', label: 'JPG' },
    { key: 'png_download', label: 'PNG' },
];

/**
 * Downloads settings tab
 * @param {Object} props - Component props
 * @param {Object} props.settings - Settings object
 * @param {Function} props.onChange - Settings change handler
 */
const DownloadsTab = ({ settings, onChange }) => {
    return (
        <div className="tab-content animate-fade-in">
            <p className="tab-description">
                {__('Select which file formats are available for download on the order page.', 'personaliseit')}
            </p>
            <div className="grid-options">
                {FORMATS.map(format => (
                    <div key={format.key} className="option-card">
                        <ToggleControl
                            label={format.label}
                            checked={settings['personaliseit_enable_' + format.key]}
                            onChange={(val) => onChange('personaliseit_enable_' + format.key, val)}
                        />
                    </div>
                ))}
            </div>
        </div>
    );
};

export default DownloadsTab;
