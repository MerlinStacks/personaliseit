/**
 * ExportRenderer - Admin component for exporting order designs
 * 
 * Provides a preview and export UI for order item personalization data.
 * Supports single-view and multi-view exports in PNG, JPG, PDF, and SVG formats.
 * 
 * Refactored in Phase 5 to use extracted hooks and components.
 */
import { useEffect, useState, useRef, useCallback } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import useFrontendStore from '../../frontend/store/useFrontendStore';
import FrontendCanvas from '../../frontend/components/FrontendCanvas';
import ExportService from '../../common/services/ExportService';

// Extracted hooks
import useExportLoader from '../hooks/useExportLoader';
import useExportActions from '../hooks/useExportActions';

// Extracted components
import { ExportViewSelector, ExportPreview, ExportActionButtons } from './export';

/**
 * Main export renderer component
 */
const ExportRenderer = () => {
    const [autoDownloadFired, setAutoDownloadFired] = useState(false);
    const [isExporting, setIsExporting] = useState(false);
    const exportStageRefs = useRef({});

    // URL parameters
    const params = new URLSearchParams(window.location.search);
    const orderId = params.get('order_id');
    const itemId = params.get('item_id');
    const urlFormat = params.get('format');

    // Store state
    const views = useFrontendStore((state) => state.views);
    const currentViewId = useFrontendStore((state) => state.currentViewId);
    const setCurrentViewId = useFrontendStore((state) => state.setCurrentViewId);
    const userInputs = useFrontendStore((state) => state.userInputs);

    // Load order data
    const { isLoading, status, setStatus } = useExportLoader(orderId, itemId);

    // Export actions
    const { handleExportSingle, handleExportAll } = useExportActions({
        exportStageRefs,
        orderId,
        itemId,
        setStatus,
        setIsExporting
    });

    // Register stage ref for a view
    const registerStageRef = useCallback((viewId, ref) => {
        exportStageRefs.current[viewId] = ref;
    }, []);

    // Auto-download if format specified in URL
    useEffect(() => {
        if (!isLoading && !autoDownloadFired && urlFormat && exportStageRefs.current[currentViewId]) {
            const timer = setTimeout(() => {
                handleExportSingle(urlFormat);
                setAutoDownloadFired(true);
                setStatus(__('Downloaded. Closing...', 'personaliseit'));
                setTimeout(() => window.close(), 500);
            }, 1500);
            return () => clearTimeout(timer);
        }
    }, [isLoading, autoDownloadFired, urlFormat, currentViewId, handleExportSingle, setStatus]);

    // Derived state
    const currentView = views.find(v => v.id === currentViewId);
    const containsImages = currentView ? ExportService.hasImageLayers(currentView, userInputs) : false;

    // Loading state
    if (isLoading) {
        return (
            <div className="personaliseit-export-loading">
                <Spinner />
                <p>{status}</p>
            </div>
        );
    }

    // Auto-download mode (minimal UI)
    if (urlFormat && !autoDownloadFired) {
        return (
            <div className="personaliseit-export-autodownload">
                <Spinner />
                <p>{status}</p>
                {views.map(view => (
                    <div key={view.id} className="personaliseit-export-hidden">
                        <ExportCanvas viewId={view.id} registerRef={registerStageRef} />
                    </div>
                ))}
            </div>
        );
    }

    return (
        <div className="personaliseit-export-wrapper">
            {/* Header */}
            <div className="personaliseit-export-header">
                <h2>{__('Export Design', 'personaliseit')}</h2>
                <span className="personaliseit-export-header__order">
                    {__('Order', 'personaliseit')} #{orderId} / {__('Item', 'personaliseit')} #{itemId}
                </span>
            </div>

            {/* View Selector */}
            <ExportViewSelector
                views={views}
                currentViewId={currentViewId}
                onViewChange={setCurrentViewId}
            />

            {/* Preview */}
            <ExportPreview currentView={currentView} />

            {/* Status */}
            {status && <p className="personaliseit-export-status">{status}</p>}

            {/* Export Actions */}
            <ExportActionButtons
                isExporting={isExporting}
                containsImages={containsImages}
                viewCount={views.length}
                onExportSingle={handleExportSingle}
                onExportAll={handleExportAll}
            />

            {/* Hidden export canvases for all views */}
            {views.map(view => (
                <div key={view.id} className="personaliseit-export-hidden">
                    <ExportCanvas viewId={view.id} registerRef={registerStageRef} />
                </div>
            ))}
        </div>
    );
};

/**
 * Hidden canvas component for a specific view (used for export)
 */
const ExportCanvas = ({ viewId, registerRef }) => {
    const stageRef = useRef();
    const setCurrentViewId = useFrontendStore((state) => state.setCurrentViewId);

    useEffect(() => {
        const prevViewId = useFrontendStore.getState().currentViewId;
        setCurrentViewId(viewId);

        const timer = setTimeout(() => {
            registerRef(viewId, stageRef);
            if (prevViewId && prevViewId !== viewId) {
                setCurrentViewId(prevViewId);
            }
        }, 100);

        return () => clearTimeout(timer);
    }, [viewId, registerRef, setCurrentViewId]);

    return <FrontendCanvas stageRef={stageRef} exportMode={true} />;
};

export default ExportRenderer;
