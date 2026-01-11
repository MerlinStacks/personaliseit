import Konva from 'konva';
import { Stage, Layer } from 'react-konva';
import useImage from 'use-image';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import usePersonaliseItStore from '../store/useFrontendStore';
import DesignRenderer from '../../common/components/DesignRenderer';

// --- HELPERS ---
// URLImage and measure helpers moved to common/components

// --- COMPONENT ---

const FrontendCanvas = ({ stageRef, exportMode = false }) => {
	const currentViewId = usePersonaliseItStore((state) => state.currentViewId);
	const setCurrentViewId = usePersonaliseItStore((state) => state.setCurrentViewId);
	const defaultProductImage = usePersonaliseItStore((state) => state.productImage);
	const userInputs = usePersonaliseItStore((state) => state.userInputs);
	const userStyles = usePersonaliseItStore((state) => state.userStyles);
	const views = usePersonaliseItStore((state) => state.views);
	const variations = usePersonaliseItStore((state) => state.variations);
	const currentVariationId = usePersonaliseItStore((state) => state.currentVariationId);
	const setCurrentVariationId = usePersonaliseItStore((state) => state.setCurrentVariationId);
	const config = usePersonaliseItStore((state) => state.config);
	const embroideryColor = usePersonaliseItStore((state) => state.embroideryColor);

	const personalisationMethod = config?.personalisationMethod || 'none';

	// Dynamic Fonts handling is now centralized in index.js via FontService
	useEffect(() => {
		if (stageRef.current) {
			document.fonts.ready.then(() => {
				stageRef.current.batchDraw();
			});
		}
	}, [config, stageRef]);

	const containerRef = useRef(null);
	const [size, setSize] = useState({ width: 400, height: 400 });

	useEffect(() => {
		if (!containerRef.current) return;
		const obs = new ResizeObserver((entries) => {
			for (let e of entries) {
				setSize({ width: e.contentRect.width, height: e.contentRect.height });
			}
		});
		obs.observe(containerRef.current);
		return () => obs.disconnect();
	}, []);

	if (!config || !views) return null;
	const currentView = views.find((v) => v.id === currentViewId) || views[0];
	if (!currentView) return null;

	const overlayImage = currentView.overlayImage;

	const layers = currentView.layers || [];
	let backgroundImage = currentView.image || defaultProductImage;
	if (currentView && variations && variations.length > 0 && currentVariationId && currentView.variationImages && currentView.variationImages[currentVariationId]) {
		backgroundImage = currentView.variationImages[currentVariationId];
	}

	const baseWidth = window.personaliseitData?.settings?.canvasWidth || 800;
	const scale = size.width / baseWidth;
	const [bgImage] = useImage(backgroundImage, 'anonymous');
	const stageHeight = bgImage ? (bgImage.height / bgImage.width) * size.width : size.width;


	// Displacement logic (userLayersRef etc) removed as it is handled in DesignRenderer

	return (
		<div className="personalizer-canvas" ref={containerRef} style={{ width: '100%', height: '100%', position: 'relative' }}>

			{variations && variations.length > 0 && (
				<div className="variation-switcher" style={{ marginBottom: 10, display: 'flex', gap: 5 }}>
					<span style={{ fontWeight: 'bold' }}>{__('Option:', 'personaliseit')}</span>
					{variations.map(v => (
						<button key={v.id} onClick={(e) => { e.preventDefault(); setCurrentVariationId(v.id); }} className={currentVariationId === v.id ? 'active' : ''} style={{ padding: '5px 10px', background: currentVariationId === v.id ? '#007cba' : '#fff', color: currentVariationId === v.id ? '#fff' : '#333' }}>{v.name}</button>
					))}
				</div>
			)}
			{views && views.length > 1 && (
				<div className="view-switcher" style={{ marginBottom: 10, display: 'flex', gap: 5 }}>
					<span style={{ fontWeight: 'bold' }}>{__('View:', 'personaliseit')}</span>
					{views.map(v => (
						<button key={v.id} onClick={(e) => { e.preventDefault(); setCurrentViewId(v.id); }} className={currentViewId === v.id ? 'active' : ''} style={{ padding: '5px 10px', background: currentViewId === v.id ? '#007cba' : '#fff', color: currentViewId === v.id ? '#fff' : '#333' }}>{v.name}</button>
					))}
				</div>
			)}


			{size.width > 0 && (
				<Stage width={size.width} height={stageHeight} ref={stageRef} scale={{ x: scale, y: scale }}>
					<Layer>
						<DesignRenderer
							layers={layers}
							userInputs={userInputs}
							userStyles={userStyles}
							personalisationMethod={personalisationMethod}
							embroideryColor={embroideryColor?.code}

							backgroundImage={backgroundImage}
							overlayImage={overlayImage}

							displacementImage={currentView.displacementImage}
							displacementScale={currentView.displacementScale}

							width={baseWidth}
							height={bgImage ? (bgImage.height / bgImage.width) * baseWidth : baseWidth}
							exportMode={exportMode}
						/>
					</Layer>
				</Stage>
			)}
		</div>
	);
};

export default FrontendCanvas;
