import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import usePersonaliseItStore from '../store/useFrontendStore';

const EMBROIDERY_COLORS = [
    { name: 'White (2149)', code: '#FFFFFF', textColor: '#333' },
    { name: 'Charcoal (2304)', code: '#36454F', textColor: '#FFF' },
    { name: 'Battleship Grey (2140)', code: '#848482', textColor: '#FFF' },
    { name: 'Black (2150)', code: '#000000', textColor: '#FFF' },
    { name: 'Canary Yellow (2018)', code: '#FFE135', textColor: '#333' },
    { name: 'Sandy Sand (2125)', code: '#F4A460', textColor: '#333' },
    { name: 'Earth (2122)', code: '#A0522D', textColor: '#FFF' },
    { name: 'Beige (2123)', code: '#F5F5DC', textColor: '#333' },
    { name: 'Gold (2022)', code: '#FFD700', textColor: '#333' },
    { name: 'Rust (2029)', code: '#B7410E', textColor: '#FFF' },
    { name: 'Baccarat Green (2214)', code: '#2E8B57', textColor: '#FFF' },
    { name: 'Frozen Ice Blue (2061)', code: '#77B5FE', textColor: '#333' },
    { name: 'Baby Blue (2090)', code: '#89CFF0', textColor: '#333' },
    { name: 'Blue Wave (2216)', code: '#007AA5', textColor: '#FFF' },
    { name: 'Eye Blue (2231)', code: '#004F98', textColor: '#FFF' },
    { name: 'Royal Blue (2069)', code: '#4169E1', textColor: '#FFF' },
    { name: 'Neon Pink (2009)', code: '#FF6EC7', textColor: '#333' },
    { name: 'Neon Orange (2153)', code: '#FF5F1F', textColor: '#333' },
    { name: 'Red Berry (2053)', code: '#990000', textColor: '#FFF' },
    { name: 'Poinsettia (2044)', code: '#DC143C', textColor: '#FFF' },
    { name: 'Maroon (2057)', code: '#800000', textColor: '#FFF' },
    { name: 'Carnation (2177)', code: '#F6A6C1', textColor: '#333' },
    { name: 'Garden Rose (2041)', code: '#C71585', textColor: '#FFF' },
    { name: 'Pale Violet (2202)', code: '#CC99CC', textColor: '#333' },
    { name: 'Royal Purple (2204)', code: '#7851A9', textColor: '#FFF' },
    { name: 'Eggplant Purple (2086)', code: '#311432', textColor: '#FFF' },
    { name: 'Neon Yellow (2001)', code: '#CCFF00', textColor: '#333' },
    { name: 'Neon Green (2004)', code: '#39FF14', textColor: '#333' },
    { name: 'Lime Green (2100)', code: '#32CD32', textColor: '#333' },
    { name: 'Forest Green (2241)', code: '#228B22', textColor: '#FFF' },
    { name: 'Deep Green (2107)', code: '#006400', textColor: '#FFF' },
    { name: 'Olive (2113)', code: '#808000', textColor: '#FFF' },
    { name: 'Navy (2073)', code: '#000080', textColor: '#FFF' },
    { name: 'Red Pink (2183)', code: '#FA2A55', textColor: '#FFF' },
    { name: 'Pink (2166)', code: '#FFC0CB', textColor: '#333' }
];

const EmbroideryPalette = () => {
    const embroideryColor = usePersonaliseItStore(state => state.embroideryColor);
    const setEmbroideryColor = usePersonaliseItStore(state => state.setEmbroideryColor);
    const activePalette = usePersonaliseItStore(state => state.activePalette);

    // Normalize colors to ensure they are objects { name, code, textColor }
    // activePalette.colors might be simple strings ['#fff', '#000'] or objects
    const rawColors = (activePalette && activePalette.colors && activePalette.colors.length > 0)
        ? activePalette.colors
        : EMBROIDERY_COLORS;

    const colorsToRender = rawColors.map(c => {
        if (typeof c === 'string') {
            return {
                name: c,
                code: c,
                textColor: (parseInt(c.substring(1), 16) > 0xffffff / 2) ? '#000' : '#FFF' // Simple contrast check
            };
        }
        return c; // Already an object
    });

    // Initialize with default color if not set
    useEffect(() => {
        if (!embroideryColor && colorsToRender.length > 0) {
            // Priority: color named 'Black', then first color
            const defaultColor = colorsToRender.find(c => c.name && c.name.toLowerCase().includes('black')) || colorsToRender[0];
            setEmbroideryColor(defaultColor);
        }
    }, [embroideryColor, colorsToRender]);

    // Local state for dropdown visibility
    const [isOpen, setIsOpen] = useState(false);

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = () => setIsOpen(false);
        if (isOpen) {
            setTimeout(() => window.addEventListener('click', handleClickOutside), 0);
        }
        return () => window.removeEventListener('click', handleClickOutside);
    }, [isOpen]);

    const selectedColor = colorsToRender.find(c => (embroideryColor?.code === c.code) || (embroideryColor === c.code)) || colorsToRender[0];

    return (
        <div className="pi-embroidery-palette control-group" style={{ position: 'relative' }}>
            <label style={{ fontWeight: '600', marginBottom: '8px', display: 'block', textTransform: 'uppercase', fontSize: '12px', letterSpacing: '0.5px' }}>
                {__('Embroidery Thread Colour', 'personaliseit')} <span style={{ color: '#e53e3e' }}>*</span>
            </label>

            {/* Main Trigger Button */}
            <button
                type="button"
                className="pi-dropdown-trigger"
                onClick={(e) => {
                    e.stopPropagation();
                    setIsOpen(!isOpen);
                }}
                style={{
                    width: '100%',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '8px 12px',
                    background: '#fff',
                    border: isOpen ? '1px solid #000' : '1px solid #cbd5e0',
                    borderRadius: '4px',
                    cursor: 'pointer',
                    transition: 'all 0.2s',
                    textAlign: 'left',
                    minHeight: '42px'
                }}
            >
                {selectedColor ? (
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <span style={{
                            width: '24px',
                            height: '24px',
                            borderRadius: '50%',
                            background: selectedColor.code,
                            border: '1px solid rgba(0,0,0,0.1)',
                            display: 'block',
                            flexShrink: 0
                        }} />
                        <span style={{ fontSize: '14px', color: '#1a202c', fontWeight: '500' }}>{selectedColor.name}</span>
                    </div>
                ) : (
                    <span>{__('Select Color', 'personaliseit')}</span>
                )}
                <span className="dashicons dashicons-arrow-down-alt2" style={{ color: '#718096', transform: isOpen ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s' }}></span>
            </button>

            {/* Dropdown Menu */}
            {isOpen && (
                <div style={{
                    position: 'absolute',
                    top: '100%',
                    left: 0,
                    right: 0,
                    marginTop: '4px',
                    background: '#fff',
                    border: '1px solid #cbd5e0',
                    borderRadius: '6px',
                    boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
                    zIndex: 1000,
                    maxHeight: '260px',
                    overflowY: 'auto'
                }}>
                    {colorsToRender.map((color, idx) => {
                        const isActive = selectedColor && selectedColor.code === color.code;
                        return (
                            <div
                                key={idx}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    setEmbroideryColor(color);
                                    setIsOpen(false);
                                }}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: '10px',
                                    padding: '8px 12px',
                                    cursor: 'pointer',
                                    background: isActive ? '#f7fafc' : '#fff',
                                    borderLeft: isActive ? '3px solid #3182ce' : '3px solid transparent',
                                    transition: 'background 0.15s'
                                }}
                                onMouseEnter={(e) => { if (!isActive) e.currentTarget.style.background = '#edf2f7'; }}
                                onMouseLeave={(e) => { if (!isActive) e.currentTarget.style.background = '#fff'; }}
                            >
                                <span style={{
                                    width: '20px',
                                    height: '20px',
                                    borderRadius: '50%',
                                    background: color.code,
                                    border: '1px solid rgba(0,0,0,0.1)',
                                    display: 'block',
                                    flexShrink: 0
                                }} />
                                <span style={{ fontSize: '13.5px', color: '#2d3748', fontWeight: isActive ? '600' : '400' }}>{color.name}</span>
                                {isActive && <span className="dashicons dashicons-yes" style={{ marginLeft: 'auto', color: '#3182ce' }}></span>}
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
};

export default EmbroideryPalette;
