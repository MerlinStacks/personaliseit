/**
 * ExportActionButtons - Export format buttons for single and multi-view exports
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Renders export action buttons for various formats
 * @param {Object} props - Component props
 * @param {boolean} props.isExporting - Whether an export is in progress
 * @param {boolean} props.containsImages - Whether current view has image layers (disables SVG)
 * @param {number} props.viewCount - Number of views (shows multi-view section if > 1)
 * @param {Function} props.onExportSingle - Single view export handler
 * @param {Function} props.onExportAll - Multi-view export handler
 */
const ExportActionButtons = ({
    isExporting,
    containsImages,
    viewCount,
    onExportSingle,
    onExportAll
}) => {
    return (
        <>
            {/* Export Current View */}
            <div className="personaliseit-export-section">
                <h3>{__('Export Current View', 'personaliseit')}</h3>
                <div className="personaliseit-export-buttons">
                    <Button
                        isPrimary
                        onClick={() => onExportSingle('png')}
                        disabled={isExporting}
                    >
                        {__('PNG', 'personaliseit')}
                    </Button>
                    <Button
                        isSecondary
                        onClick={() => onExportSingle('jpg')}
                        disabled={isExporting}
                    >
                        {__('JPG', 'personaliseit')}
                    </Button>
                    <Button
                        isSecondary
                        onClick={() => onExportSingle('pdf')}
                        disabled={isExporting}
                    >
                        {__('PDF', 'personaliseit')}
                    </Button>
                    <Button
                        isSecondary
                        onClick={() => onExportSingle('svg')}
                        disabled={isExporting || containsImages}
                        title={containsImages
                            ? __('SVG not available for designs with images', 'personaliseit')
                            : ''
                        }
                    >
                        {__('SVG', 'personaliseit')}
                    </Button>
                </div>
            </div>

            {/* Export All Views (only if multiple views) */}
            {viewCount > 1 && (
                <div className="personaliseit-export-section">
                    <h3>{__('Export All Views', 'personaliseit')} ({viewCount})</h3>
                    <div className="personaliseit-export-buttons">
                        <Button
                            isPrimary
                            onClick={() => onExportAll('zip-png')}
                            disabled={isExporting}
                        >
                            {__('ZIP (PNG)', 'personaliseit')}
                        </Button>
                        <Button
                            isSecondary
                            onClick={() => onExportAll('zip-jpg')}
                            disabled={isExporting}
                        >
                            {__('ZIP (JPG)', 'personaliseit')}
                        </Button>
                        <Button
                            isSecondary
                            onClick={() => onExportAll('pdf')}
                            disabled={isExporting}
                        >
                            {__('Multi-Page PDF', 'personaliseit')}
                        </Button>
                    </div>
                </div>
            )}

            {/* SVG Notice */}
            <p className="personaliseit-export-note">
                {containsImages
                    ? __('Note: SVG export is disabled because this design contains images.', 'personaliseit')
                    : __('Note: SVG export converts text to vector paths for production use.', 'personaliseit')
                }
            </p>
        </>
    );
};

export default ExportActionButtons;
