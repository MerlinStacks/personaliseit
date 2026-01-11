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
        <div className="moon-phase-tool-container" style={{ background: '#f8f9fa', padding: '10px', borderRadius: '4px', border: '1px solid #eee', marginTop: '10px' }}>
            <label style={{ display: 'block', fontWeight: 'bold', marginBottom: '5px' }}>{__('Select Date', 'personaliseit')}</label>

            <input
                type="date"
                className="pi-modern-input"
                style={{ width: '100%', marginBottom: '10px' }}
                value={date}
                onChange={(e) => setDate(e.target.value)}
            />

            <div className="moon-preview" style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', marginBottom: '10px', padding: '10px', background: '#fff', border: '1px solid #ddd', borderRadius: '4px' }}>
                <div style={{ fontWeight: '600', marginBottom: '5px' }}>{getMoonPhaseName(phaseIndex)}</div>
                {previewUrl && (
                    <img
                        src={previewUrl}
                        alt="Moon Phase"
                        style={{ width: '80px', height: '80px' }}
                        onError={(e) => {
                            e.target.onerror = null;
                            e.target.style.display = 'none';
                            e.target.parentNode.innerHTML += '<span style="color:red; font-size:10px">Image Not Found</span>';
                        }}
                    />
                )}
            </div>

            <div style={{ display: 'flex', gap: '5px' }}>
                <button
                    className="pi-btn primary small"
                    onClick={handleApply}
                    style={{ flex: 1 }}
                >
                    {__('Apply Moon Phase', 'personaliseit')}
                </button>
                <button
                    className="pi-btn secondary small"
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
