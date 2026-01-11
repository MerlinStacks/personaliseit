import { useRef } from '@wordpress/element';
import { Spinner, SnackbarList } from '@wordpress/components';
import CanvasStage from './CanvasStage';
import SidebarLeft from './designer/SidebarLeft';
import SidebarRight from './designer/SidebarRight';
import DesignerHeader from './designer/DesignerHeader';
import DesignerToolbar from './designer/DesignerToolbar';
import useDesignerLogic from '../hooks/useDesignerLogic';

/**
 * Designer Component (Refactored)
 * Presentational component that uses useDesignerLogic for state management.
 */
const Designer = () => {
	const canvasRef = useRef();
	const {
		selectedProduct,
		orderMode,
		templateId,
		isSaving,
		isLoading,
		notices,
		fonts,
		mobileView,
		setMobileView,
		showGrid,
		setShowGrid,
		snapToGrid,
		setSnapToGrid,
		exportConfig,
		importConfig,
		addNotice,
		removeNotice,
		handleSave,
		setIsLoading
	} = useDesignerLogic(canvasRef);

	return (
		<div className={`personaliseit-designer-container personaliseit-mobile-view-${mobileView}`}>

			{/* Main Layout */}
			<div className="personaliseit-designer__workspace">

				{/* 1. LEFT SIDEBAR */}
				<div className={`personaliseit-designer__sidebar personaliseit-designer__sidebar--left ${mobileView === 'left' ? 'mobile-visible' : ''}`}>
					<DesignerHeader mobileView="left" setMobileView={setMobileView} selectedProduct={selectedProduct} templateId={templateId} />
					<div className="personaliseit-designer__content">
						<SidebarLeft
							selectedProduct={selectedProduct}
							showGrid={showGrid}
							setShowGrid={setShowGrid}
							snapToGrid={snapToGrid}
							setSnapToGrid={setSnapToGrid}
						/>
					</div>
				</div>

				{/* 2. CENTER CANVAS */}
				<div className={`personaliseit-designer__canvas ${mobileView === 'canvas' ? 'mobile-visible' : ''}`}>
					{isLoading && (
						<div className="absolute-center-loader" style={{ position: 'absolute', zIndex: 100 }}>
							<Spinner />
						</div>
					)}

					<CanvasStage ref={canvasRef} showGrid={showGrid} snapToGrid={snapToGrid} />

					<DesignerToolbar
						showGrid={showGrid}
						setShowGrid={setShowGrid}
						snapToGrid={snapToGrid}
						setSnapToGrid={setSnapToGrid}
						canvasRef={canvasRef}
					/>
				</div>

				{/* 3. RIGHT SIDEBAR */}
				<div className={`personaliseit-designer__sidebar personaliseit-designer__sidebar--right ${mobileView === 'right' ? 'mobile-visible' : ''}`}>
					<DesignerHeader
						mobileView="right"
						setMobileView={setMobileView}
						selectedProduct={selectedProduct}
						templateId={templateId}
						orderMode={orderMode}
						isSaving={isSaving}
						handleSave={handleSave}
						canvasRef={canvasRef}
						addNotice={addNotice}
						exportConfig={exportConfig}
						importConfig={importConfig}
					/>
					<div className="personaliseit-designer__content">
						<SidebarRight
							fonts={fonts}
							addNotice={addNotice}
							setIsLoading={setIsLoading}
						/>
					</div>
				</div>
			</div>

			{/* Mobile Navigation Bar */}
			<div className="personaliseit-mobile-nav">
				{['left', 'canvas', 'right'].map(view => (
					<button
						key={view}
						className={`mobile-nav-item ${mobileView === view ? 'active' : ''}`}
						onClick={() => setMobileView(view)}
					>
						<span className={`dashicons dashicons-${view === 'left' ? 'admin-settings' : view === 'canvas' ? 'art' : 'layers'}`}></span>
					</button>
				))}
			</div>

			{/* Toast Notices */}
			<div className="designer-notices" style={{ position: 'fixed', bottom: 20, right: 20, zIndex: 1000 }}>
				<SnackbarList notices={notices} onRemove={removeNotice} />
			</div>
		</div>
	);
};

export default Designer;
