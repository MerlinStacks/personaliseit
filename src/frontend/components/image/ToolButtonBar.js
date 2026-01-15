/**
 * ToolButtonBar - Quick access buttons for image tools
 * 
 * Renders the row of tool buttons (AI Gen, Spotify, Moon, Face Cut).
 * 
 * @module ToolButtonBar
 */
import { __ } from '@wordpress/i18n';

/**
 * Tool button bar component
 * @param {Object} props - Component props
 * @param {boolean} props.enableSpotify - Whether Spotify is enabled
 * @param {Function} props.onAiGenerate - AI generate click handler
 * @param {Function} props.onSpotify - Spotify click handler
 * @param {Function} props.onMoonPhase - Moon phase click handler
 * @param {Function} props.onFaceCutout - Face cutout click handler
 */
const ToolButtonBar = ({
    enableSpotify,
    onAiGenerate,
    onSpotify,
    onMoonPhase,
    onFaceCutout
}) => {
    return (
        <div className="pi-flex-row pi-gap-xs">
            <button
                type="button"
                className="pi-btn secondary small pi-flex-1"
                onClick={onAiGenerate}
            >
                <span className="dashicons dashicons-art" aria-hidden="true"></span>
                {__('AI Gen', 'personaliseit')}
            </button>

            {enableSpotify && (
                <button
                    type="button"
                    className="pi-btn secondary small pi-flex-1"
                    onClick={onSpotify}
                >
                    <span className="dashicons dashicons-format-audio" aria-hidden="true"></span>
                    {__('Spotify', 'personaliseit')}
                </button>
            )}

            <button
                type="button"
                className="pi-btn secondary small pi-flex-1"
                onClick={onMoonPhase}
            >
                <span className="dashicons dashicons-update" aria-hidden="true"></span>
                {__('Moon', 'personaliseit')}
            </button>

            <button
                type="button"
                className="pi-btn secondary small pi-flex-1"
                onClick={onFaceCutout}
            >
                <span className="dashicons dashicons-admin-users" aria-hidden="true"></span>
                {__('Face Cut', 'personaliseit')}
            </button>
        </div>
    );
};

export default ToolButtonBar;
