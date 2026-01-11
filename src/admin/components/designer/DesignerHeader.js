import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import useStore from '../../store/useStore';
import SaveTemplateModal from '../modals/SaveTemplateModal';
import LoadTemplateModal from '../modals/LoadTemplateModal';
import { useState } from '@wordpress/element';

const DesignerHeader = ({
    mobileView,
    setMobileView,
    selectedProduct,
    templateId,
    orderMode,
    isSaving,
    handleSave,
    canvasRef,
    addNotice,
    exportConfig,
    importConfig
}) => {
    const [isSaveModalOpen, setIsSaveModalOpen] = useState(false);
    const [isLoadModalOpen, setIsLoadModalOpen] = useState(false);

    // Helper for High-Res Download (Order Mode)
    const handleDownload = () => {
        if (canvasRef.current) {
            const stage = canvasRef.current.getStage();
            // Hide UI elements during capture
            const tr = stage.findOne('Transformer');
            if (tr) tr.hide();
            const bg = stage.findOne('.background-image');
            const overlay = stage.findOne('.overlay-image');
            if (bg) bg.hide();
            if (overlay) overlay.hide();

            const data = stage.toDataURL({ pixelRatio: 3 });
            const link = document.createElement('a');
            link.download = `order-${orderMode.orderId}-item-${orderMode.itemId}.png`;
            link.href = data;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Restore UI
            if (tr) tr.show();
            if (bg) bg.show();
            if (overlay) overlay.show();
        }
    };

    // Logic for Left Sidebar Header
    if (mobileView === 'left') {
        return (
            <div className="personaliseit-designer__header" style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '10px' }}>
                <Button
                    href={templateId ? 'admin.php?page=personaliseit-templates' : `/wp-admin/post.php?post=${selectedProduct?.id}&action=edit`}
                    icon="arrow-left-alt2"
                    label={__('Back', 'personaliseit')}
                    isSmall
                />
                <h3 style={{ margin: 0 }}>{__('Configuration', 'personaliseit')}</h3>
                <Button icon="no" className="mobile-only-close" onClick={() => setMobileView('canvas')} label={__('Close', 'personaliseit')} />
            </div>
        );
    }

    // Logic for Right Sidebar Header (Actions)
    if (mobileView === 'right') {
        return (
            <>
                <div className="personaliseit-designer__header" style={{ display: 'flex', flexDirection: 'column', gap: '5px' }}>
                    <div style={{ display: 'flex', gap: '5px', justifyContent: 'flex-end', flexWrap: 'nowrap', width: '100%' }}>
                        <div style={{ flex: 1, display: 'flex', alignItems: 'center' }}>
                            <Button icon="no" className="mobile-only-close" onClick={() => setMobileView('canvas')} label={__('Close', 'personaliseit')} />
                        </div>

                        {orderMode ? (
                            <Button
                                isPrimary
                                isSmall
                                icon="download"
                                onClick={handleDownload}
                                label={__('Download Hi-Res', 'personaliseit')}
                            />
                        ) : (
                            <>
                                <Button
                                    isSecondary
                                    isSmall
                                    icon="saved"
                                    onClick={() => setIsSaveModalOpen(true)}
                                    disabled={isSaving}
                                    label={__('Save Tpl', 'personaliseit')}
                                />
                                <Button
                                    isSecondary
                                    isSmall
                                    icon="layout"
                                    onClick={() => setIsLoadModalOpen(true)}
                                    disabled={isSaving}
                                    label={__('Load Tpl', 'personaliseit')}
                                />
                                <Button
                                    isPrimary
                                    isSmall
                                    disabled={isSaving}
                                    onClick={handleSave}
                                    isBusy={isSaving}
                                    style={{ whiteSpace: 'nowrap' }}
                                >
                                    {isSaving ? __('Saving', 'personaliseit') : __('Save', 'personaliseit')}
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {/* Modals are managed here to keep Designer clean */}
                <SaveTemplateModal
                    isOpen={isSaveModalOpen}
                    onClose={() => setIsSaveModalOpen(false)}
                    addNotice={addNotice}
                    exportConfig={exportConfig}
                    captureSnapshot={() => {
                        if (canvasRef.current) {
                            const stage = canvasRef.current.getStage();
                            if (stage) {
                                const tr = stage.findOne('Transformer');
                                if (tr) tr.hide();
                                const data = stage.toDataURL({ pixelRatio: 0.2, mimeType: 'image/jpeg', quality: 0.7 });
                                if (tr) tr.show();
                                return data;
                            }
                        }
                        return null;
                    }}
                />
                <LoadTemplateModal
                    isOpen={isLoadModalOpen}
                    onClose={() => setIsLoadModalOpen(false)}
                    addNotice={addNotice}
                    importConfig={importConfig}
                />
            </>
        );
    }

    return null;
};

export default DesignerHeader;
