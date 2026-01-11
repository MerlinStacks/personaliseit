import { useState, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import StyleTab from './tabs/StyleTab';
import TransformTab from './tabs/TransformTab';
import EffectsTab from './tabs/EffectsTab';
import SettingsTab from './tabs/SettingsTab';

const PropertiesPanel = ({
    selectedLayer,
    updateLayer,
    removeLayer,
    fonts,
    personalisationMethod,
    allCategories,
    setIsLoading
}) => {
    const [activeTab, setActiveTab] = useState('style');

    useEffect(() => {
        setActiveTab('style');
    }, [selectedLayer?.id]);

    if (!selectedLayer) {
        return (
            <div className="personaliseit-designer__properties-placeholder">
                <p>{__('Select a layer to edit properties', 'personaliseit')}</p>
            </div>
        );
    }

    return (
        <div className="personaliseit-designer__properties">
            {/* Header Actions */}
            <div className="personaliseit-designer__properties-header" style={{ padding: '10px 15px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid #eee' }}>
                <strong style={{ fontSize: '12px', textTransform: 'uppercase', color: '#666' }}>{__('PROPERTIES', 'personaliseit')}</strong>
                <Button
                    isSmall
                    isDestructive
                    icon="trash"
                    className="delete-layer-btn"
                    onClick={() => removeLayer(selectedLayer.id)}
                    label={__('Delete Layer', 'personaliseit')}
                />
            </div>

            {/* Tab Navigation */}
            <div className="personaliseit-designer__properties-tabs">
                <Button
                    className={`personaliseit-designer__tab-btn ${activeTab === 'style' ? 'active' : ''}`}
                    icon={selectedLayer.type === 'text' ? 'editor-textcolor' : 'format-image'}
                    onClick={() => setActiveTab('style')}
                    label={__('Style', 'personaliseit')}
                >
                    {__('Style', 'personaliseit')}
                </Button>
                <Button
                    className={`personaliseit-designer__tab-btn ${activeTab === 'transform' ? 'active' : ''}`}
                    icon="move"
                    onClick={() => setActiveTab('transform')}
                    label={__('Transform', 'personaliseit')}
                >
                    {__('Position', 'personaliseit')}
                </Button>
                {personalisationMethod !== 'embroidery' && (
                    <Button
                        className={`personaliseit-designer__tab-btn ${activeTab === 'effects' ? 'active' : ''}`}
                        icon="star-filled"
                        onClick={() => setActiveTab('effects')}
                        label={__('Effects', 'personaliseit')}
                    >
                        {__('Effects', 'personaliseit')}
                    </Button>
                )}
                <Button
                    className={`personaliseit-designer__tab-btn ${activeTab === 'settings' ? 'active' : ''}`}
                    icon="admin-generic"
                    onClick={() => setActiveTab('settings')}
                    label={__('Settings', 'personaliseit')}
                    style={{ minWidth: '40px' }}
                >
                    {__('Adv.', 'personaliseit')}
                </Button>
            </div>

            {/* Tab Content Area (Scrollable) */}
            <div className="personaliseit-designer__properties-content">
                {activeTab === 'style' && (
                    <StyleTab
                        selectedLayer={selectedLayer}
                        updateLayer={updateLayer}
                        fonts={fonts}
                        personalisationMethod={personalisationMethod}
                        allCategories={allCategories}
                    />
                )}

                {activeTab === 'transform' && (
                    <TransformTab
                        selectedLayer={selectedLayer}
                        updateLayer={updateLayer}
                    />
                )}

                {activeTab === 'effects' && (
                    <EffectsTab
                        selectedLayer={selectedLayer}
                        updateLayer={updateLayer}
                        personalisationMethod={personalisationMethod}
                        setIsLoading={setIsLoading}
                    />
                )}

                {activeTab === 'settings' && (
                    <SettingsTab
                        selectedLayer={selectedLayer}
                        updateLayer={updateLayer}
                    />
                )}
            </div>
        </div>
    );
};

export default PropertiesPanel;
