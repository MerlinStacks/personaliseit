import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getMoonPhase, getMoonPhaseName } from '../utils/moonPhase';

const MoonPhaseTool = ({ onSelect, onCancel }) => {
    // Default to today
    const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
    const [phaseIndex, setPhaseIndex] = useState(0);
    const [previewUrl, setPreviewUrl] = useState('');

    useEffect(() => {
        if (!date) return;
        const d = new Date(date);
        const idx = getMoonPhase(d);
        setPhaseIndex(idx);

        // Construct Asset URL using local plugin path
        // Assuming plugin URL is available via window.personaliseitData.pluginUrl
        // Fallback or todo: Pass this cleanly. For now, assuming standard WP struct or using settings.
        const pluginUrl = window.personaliseitData?.pluginUrl || '/wp-content/plugins/personaliseit';
        const url = `${pluginUrl}/assets/moon-phases/phase-${idx}.svg`;
        setPreviewUrl(url);

    }, [date]);

    const handleApply = () => {
        if (previewUrl) {
            onSelect(previewUrl);
        }
    };

    return (
        <div className="pi-tool-card moon-phase-tool-container">
            <div className="pi-tool-card__header">
                <span className="dashicons dashicons-calendar-alt" />
                {__('Select Date', 'personaliseit')}
            </div>

            <input
                type="date"
                className="pi-modern-input"
                value={date}
                onChange={(e) => setDate(e.target.value)}
            />

            <div className="pi-tool-card__preview">
                <span className="pi-phase-badge">{getMoonPhaseName(phaseIndex)}</span>
                {previewUrl && (
                    <img
                        src={previewUrl}
                        alt="Moon Phase"
                        style={{ width: '100px', height: '100px', marginTop: '12px' }}
                        onError={(e) => {
                            e.target.onerror = null;
                            e.target.style.display = 'none';
                        }}
                    />
                )}
            </div>

            <div className="pi-tool-card__actions">
                <button
                    className="pi-btn primary"
                    onClick={handleApply}
                    style={{ flex: 1 }}
                >
                    {__('Apply Moon Phase', 'personaliseit')}
                </button>
                <button
                    className="pi-btn secondary"
                    onClick={onCancel}
                    style={{ flex: 1 }}
                >
                    {__('Cancel', 'personaliseit')}
                </button>
            </div>
        </div>
    );
};

export default MoonPhaseTool;
