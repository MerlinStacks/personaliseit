/**
 * ExportPreview - Preview panel for the export renderer
 */
import { __ } from '@wordpress/i18n';
import FrontendCanvas from '../../../frontend/components/FrontendCanvas';

/**
 * Renders the design preview in a bordered container
 * @param {Object} props - Component props
 * @param {Object} props.currentView - Current view configuration
 */
const ExportPreview = ({ currentView }) => {
    return (
        <div className="personaliseit-export-preview">
            <div className="personaliseit-export-preview__header">
                {currentView?.name || __('Preview', 'personaliseit')}
            </div>
            <div className="personaliseit-export-preview__canvas">
                <div className="personaliseit-export-preview__inner">
                    <FrontendCanvas stageRef={{ current: null }} exportMode={false} />
                </div>
            </div>
        </div>
    );
};

export default ExportPreview;
