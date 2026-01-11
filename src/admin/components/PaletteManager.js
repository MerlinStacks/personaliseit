import { useState, useEffect } from '@wordpress/element';
import {
    Button,
    TextControl,
    ColorPicker,
    Card,
    CardBody,
    CardHeader,
    Spinner,
    Notice,
    Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const PaletteManager = () => {
    const [palettes, setPalettes] = useState([]);
    const [selectedPalette, setSelectedPalette] = useState(null); // { id, title, colors: [] }
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [notice, setNotice] = useState(null);

    // Temp color for adding new color
    const [newColor, setNewColor] = useState('#000000');
    const [newColorName, setNewColorName] = useState('');

    useEffect(() => {
        fetchPalettes();
    }, []);

    const fetchPalettes = async () => {
        try {
            const data = await apiFetch({ path: '/personaliseit/v1/palettes' });
            setPalettes(data);
            setIsLoading(false);
        } catch (err) {
            console.error(err);
            setNotice({ status: 'error', text: 'Error fetching palettes' });
            setIsLoading(false);
        }
    };

    const handleSelectPalette = (palette) => {
        // Normalize colors to ensure they are editable objects
        const normalizedColors = (palette.colors || []).map(c =>
            typeof c === 'string' ? { name: c, code: c } : c
        );
        setSelectedPalette({ ...palette, colors: normalizedColors });
        setNotice(null);
    };

    const handleCreatePalette = () => {
        setSelectedPalette({ id: 'new', title: 'New Palette', colors: [] });
        setNotice(null);
    };

    const handleSave = async () => {
        if (!selectedPalette) return;

        setIsSaving(true);
        try {
            let result;
            if (selectedPalette.id === 'new') {
                result = await apiFetch({
                    path: '/personaliseit/v1/palettes',
                    method: 'POST',
                    data: {
                        title: selectedPalette.title,
                        colors: selectedPalette.colors,
                    },
                });
                setPalettes([...palettes, result]);
                setSelectedPalette(result);
            } else {
                result = await apiFetch({
                    path: `/personaliseit/v1/palettes/${selectedPalette.id}`,
                    method: 'POST', // or PUT, but WP REST often uses POST for update with ID
                    data: {
                        title: selectedPalette.title,
                        colors: selectedPalette.colors,
                    },
                });
                setPalettes(
                    palettes.map((p) =>
                        p.id === selectedPalette.id ? result : p
                    )
                );
                setSelectedPalette(result);
            }
            setNotice({ status: 'success', text: __('Palette saved.', 'personaliseit') });
        } catch (err) {
            setNotice({ status: 'error', text: err.message });
        } finally {
            setIsSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!selectedPalette || selectedPalette.id === 'new') {
            setSelectedPalette(null);
            return;
        }

        if (!confirm(__('Are you sure you want to delete this palette?', 'personaliseit'))) return;

        setIsSaving(true);
        try {
            await apiFetch({
                path: `/personaliseit/v1/palettes/${selectedPalette.id}`,
                method: 'DELETE',
            });
            setPalettes(palettes.filter((p) => p.id !== selectedPalette.id));
            setSelectedPalette(null);
            setNotice({ status: 'success', text: __('Palette deleted.', 'personaliseit') });
        } catch (err) {
            setNotice({ status: 'error', text: err.message });
        } finally {
            setIsSaving(false);
        }
    };

    const addColor = () => {
        if (!newColor) return;

        // Create color object
        const colorToAdd = {
            name: newColorName.trim() || newColor, // Fallback to hex if no name
            code: newColor
        };

        // Check if color code already exists
        if (selectedPalette.colors.some(c => c.code === colorToAdd.code)) {
            // Optional: alert user? For now just return or allow duplicates if names different?
            // Let's prevent exact duplicate codes for sanity
            alert(__('This color code already exists in the palette.', 'personaliseit'));
            return;
        }

        setSelectedPalette({
            ...selectedPalette,
            colors: [...selectedPalette.colors, colorToAdd],
        });
        setNewColorName(''); // Reset name input
    };

    const removeColor = (codeToRemove) => {
        setSelectedPalette({
            ...selectedPalette,
            colors: selectedPalette.colors.filter((c) => c.code !== codeToRemove),
        });
    };

    if (isLoading) {
        return (
            <div style={{ padding: '20px', textAlign: 'center' }}>
                <Spinner />
            </div>
        );
    }

    return (
        <div className="wrap personaliseit-palettes" style={{ display: 'flex', height: 'calc(100vh - 100px)', gap: '20px', padding: '20px' }}>

            {/* Sidebar List */}
            <div style={{ width: '250px', borderRight: '1px solid #ddd', paddingRight: '20px', overflowY: 'auto' }}>
                <Button
                    isPrimary
                    onClick={handleCreatePalette}
                    style={{ width: '100%', marginBottom: '20px' }}
                >
                    {__('Add New Palette', 'personaliseit')}
                </Button>

                <div className="palette-list">
                    {palettes.map((palette) => (
                        <div
                            key={palette.id}
                            onClick={() => handleSelectPalette(palette)}
                            style={{
                                padding: '10px',
                                cursor: 'pointer',
                                background: selectedPalette && selectedPalette.id === palette.id ? '#f0f0f1' : 'transparent',
                                borderBottom: '1px solid #eee',
                            }}
                        >
                            <strong>{palette.title}</strong>
                            <div style={{ display: 'flex', gap: '2px', marginTop: '5px' }}>
                                {palette.colors.slice(0, 5).map((c, i) => {
                                    const code = typeof c === 'string' ? c : c.code;
                                    return (
                                        <div key={i} style={{ width: '15px', height: '15px', background: code, borderRadius: '50%', border: '1px solid #ccc' }} />
                                    );
                                })}
                                {palette.colors.length > 5 && <span style={{ fontSize: '10px', color: '#888' }}>+{palette.colors.length - 5}</span>}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Editor Area */}
            <div style={{ flex: 1, overflowY: 'auto' }}>
                {notice && (
                    <Notice status={notice.status} onRemove={() => setNotice(null)}>
                        {notice.text}
                    </Notice>
                )}

                {!selectedPalette ? (
                    <div style={{ color: '#666', marginTop: '50px', textAlign: 'center' }}>
                        {__('Select a palette to edit or create a new one.', 'personaliseit')}
                    </div>
                ) : (
                    <Card>
                        <CardHeader>
                            <div style={{ display: 'flex', justifyContent: 'space-between', width: '100%', alignItems: 'center' }}>
                                <h2 style={{ margin: 0 }}>
                                    {selectedPalette.id === 'new' ? __('Create New Palette', 'personaliseit') : __('Edit Palette', 'personaliseit')}
                                </h2>
                                <Button isPrimary onClick={handleSave} isBusy={isSaving} isSmall>
                                    {__('Save', 'personaliseit')}
                                </Button>
                            </div>
                        </CardHeader>
                        <CardBody>
                            <TextControl
                                label={__('Palette Name', 'personaliseit')}
                                value={selectedPalette.title}
                                onChange={(val) => setSelectedPalette({ ...selectedPalette, title: val })}
                            />

                            <hr style={{ margin: '20px 0' }} />

                            <h3>{__('Colors', 'personaliseit')}</h3>
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px', marginBottom: '20px' }}>
                                {selectedPalette.colors.map((color, idx) => (
                                    <div
                                        key={idx}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            padding: '5px',
                                            border: '1px solid #ddd',
                                            borderRadius: '4px',
                                            background: '#fff',
                                            gap: '10px'
                                        }}
                                    >
                                        <div
                                            style={{
                                                width: '30px',
                                                height: '30px',
                                                background: color.code,
                                                border: '1px solid #ddd',
                                                borderRadius: '50%',
                                            }}
                                        />
                                        <div style={{ fontSize: '12px' }}>
                                            <strong>{color.name}</strong>
                                            <div style={{ color: '#888', fontFamily: 'monospace' }}>{color.code}</div>
                                        </div>
                                        <Button
                                            icon="trash"
                                            isSmall
                                            isDestructive
                                            variant="tertiary"
                                            onClick={() => {
                                                if (confirm(`Remove color ${color.name}?`)) removeColor(color.code);
                                            }}
                                            label={__('Remove', 'personaliseit')}
                                        />
                                    </div>
                                ))}
                                {selectedPalette.colors.length === 0 && <p style={{ color: '#888' }}>{__('No colors added yet.', 'personaliseit')}</p>}
                            </div>

                            <div style={{ display: 'flex', gap: '20px', alignItems: 'flex-start', background: '#f9f9f9', padding: '15px', borderRadius: '4px' }}>
                                <div>
                                    <h4 style={{ marginTop: 0 }}>Add Color</h4>
                                    <div style={{ marginBottom: '10px' }}>
                                        <TextControl
                                            label="Color Name"
                                            value={newColorName}
                                            onChange={setNewColorName}
                                            placeholder="e.g. Midnight Blue"
                                        />
                                    </div>
                                    <ColorPicker
                                        color={newColor}
                                        onChangeComplete={(color) => setNewColor(color.hex)}
                                        disableAlpha
                                    />
                                    <Button isSecondary onClick={addColor} style={{ marginTop: '10px' }}>
                                        {__('Add Selected Color', 'personaliseit')}
                                    </Button>
                                </div>
                                <div style={{ paddingTop: '30px' }}>
                                    <p>Selected: <strong>{newColor}</strong></p>
                                </div>
                            </div>

                            <div style={{ marginTop: '30px', display: 'flex', gap: '10px' }}>
                                <Button isPrimary onClick={handleSave} isBusy={isSaving}>
                                    {__('Save Palette', 'personaliseit')}
                                </Button>
                                {selectedPalette.id !== 'new' && (
                                    <Button isDestructive isLink onClick={handleDelete}>
                                        {__('Delete Palette', 'personaliseit')}
                                    </Button>
                                )}
                            </div>
                        </CardBody>
                    </Card>
                )}
            </div>
        </div>
    );
};

export default PaletteManager;
