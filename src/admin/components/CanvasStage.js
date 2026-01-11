import { forwardRef, useImperativeHandle, useEffect, useRef, Fragment, useState } from '@wordpress/element';
import {
	Stage,
	Layer,
	Image as KonvaImage,
	Rect,
	Transformer,
	Text,
	TextPath,
	Group,
	Line,
} from 'react-konva';
import useImage from 'use-image';
import { __ } from '@wordpress/i18n';
import useStore from '../store/useStore';
import Ruler from './Ruler';
import DesignRenderer from '../../common/components/DesignRenderer';


// --- COMPONENT ---

const CanvasStage = forwardRef(({ showGrid, snapToGrid }, ref) => {
	const selectedProduct = useStore((state) => state.selectedProduct);
	const variations = useStore((state) => state.variations);
	const currentVariationId = useStore((state) => state.currentVariationId);
	const views = useStore((state) => state.views);
	const currentViewId = useStore((state) => state.currentViewId);
	const updateLayer = useStore((state) => state.updateLayer);
	const selectedLayerId = useStore((state) => state.selectedLayerId);
	const selectedLayerIds = useStore((state) => state.selectedLayerIds || []);
	const toggleLayerSelection = useStore((state) => state.toggleLayerSelection);
	const clearSelection = useStore((state) => state.clearSelection);
	const settings = useStore((state) => state.settings); // Keep settings for canvasWidth
	const personalisationMethod = useStore((state) => state.personalisationMethod); // Add this
	const setSelectedLayerId = useStore((state) => state.setSelectedLayerId);

	const gridSize = 20;

	const currentView = views.find((v) => v.id === currentViewId);
	const layers = currentView ? currentView.layers : [];
	const overlayImage = currentView ? currentView.overlayImage : null;

	let backgroundImage = currentView ? currentView.image : '';
	if (!backgroundImage && selectedProduct) {
		if (selectedProduct.image) backgroundImage = selectedProduct.image;
		else if (selectedProduct.images && selectedProduct.images.length > 0)
			backgroundImage = selectedProduct.images[0].src;
		else if (selectedProduct.featured_src)
			backgroundImage = selectedProduct.featured_src;
	}

	if (
		currentView &&
		variations.length > 0 &&
		currentVariationId &&
		currentView.variationImages &&
		currentView.variationImages[currentVariationId]
	) {
		backgroundImage = currentView.variationImages[currentVariationId];
	}

	const [image] = useImage(backgroundImage);
	const stageRef = useRef();
	const trRef = useRef();

	const [stageScale, setStageScale] = useState(1);
	const [stagePos, setStagePos] = useState({ x: 0, y: 0 });
	const [containerSize, setContainerSize] = useState({ width: 800, height: 600 });
	const containerRef = useRef(null);
	const observerRef = useRef(null);
	const [guides, setGuides] = useState([]);

	useEffect(() => {
		if (trRef.current && stageRef.current) {
			const nodes = selectedLayerIds
				.map((id) => stageRef.current.findOne('#' + id))
				.filter((node) => node !== undefined);

			if (nodes.length > 0) {
				trRef.current.nodes(nodes);
				trRef.current.getLayer().batchDraw();
			} else {
				trRef.current.nodes([]);
				trRef.current.getLayer().batchDraw();
			}
		}
	}, [selectedLayerIds, layers]); // Depend on plural ID


	const checkDeselect = (e) => {
		const clickedOnEmpty = e.target === e.target.getStage();
		if (clickedOnEmpty) {
			clearSelection();
		}
	};

	const stageHeight = image
		? Math.round(image.height * (settings.canvasWidth / image.width))
		: settings.canvasWidth;

	// Zoom/Fit Logic
	const fitToScreen = () => {
		if (!containerRef.current || !image || image.width === 0) return;
		try {
			const containerW = containerRef.current.offsetWidth;
			const containerH = containerRef.current.offsetHeight;
			const padding = 40;
			const availW = containerW - padding;
			const availH = containerH - padding;
			const canvasW = settings.canvasWidth;
			const canvasH = image.height * (settings.canvasWidth / image.width);
			const scale = Math.min(availW / canvasW, availH / canvasH) * 0.9;
			if (!isFinite(scale)) return;
			setStageScale(scale);
			setStagePos({
				x: (availW - canvasW * scale) / 2 + 20,
				y: (availH - canvasH * scale) / 2 + 20,
			});
		} catch (e) {
			console.error(e);
		}
	};

	useImperativeHandle(ref, () => ({
		zoomIn: () => setStageScale(s => s * 1.2),
		zoomOut: () => setStageScale(s => s / 1.2),
		fitToScreen: () => fitToScreen(),
		getStage: () => stageRef.current
	}));

	useEffect(() => {
		if (image && containerRef.current) {
			setTimeout(fitToScreen, 100);
		}
	}, [image]);

	useEffect(() => {
		if (!containerRef.current) return;
		observerRef.current = new ResizeObserver((entries) => {
			for (let entry of entries) {
				setContainerSize({
					width: entry.contentRect.width,
					height: entry.contentRect.height,
				});
			}
		});
		observerRef.current.observe(containerRef.current);
		return () => observerRef.current.disconnect();
	}, []);

	const handleWheel = (e) => {
		e.evt.preventDefault();
		if (!stageRef.current) return;
		const scaleBy = 1.1;
		const stage = stageRef.current;
		const oldScale = stage.scaleX();
		const pointer = stage.getPointerPosition();
		if (!pointer) return;
		const newScale = e.evt.deltaY < 0 ? oldScale * scaleBy : oldScale / scaleBy;
		setStageScale(newScale);
		const mousePointTo = {
			x: (pointer.x - stage.x()) / oldScale,
			y: (pointer.y - stage.y()) / oldScale,
		};
		const newPos = {
			x: pointer.x - mousePointTo.x * newScale,
			y: pointer.y - mousePointTo.y * newScale,
		};
		setStagePos(newPos);
	};

	const handleDragMove = (e) => {
		setGuides([]);
		const node = e.target;
		if (snapToGrid) {
			node.position({
				x: Math.round(node.x() / gridSize) * gridSize,
				y: Math.round(node.y() / gridSize) * gridSize,
			});
			return;
		}
		// Do not update store on drag move to prevent re-renders
	};

	const handleDragEnd = (e) => {
		const node = e.target;
		updateLayer(node.id(), { x: node.x(), y: node.y() });
	};

	const handleTransformEnd = (e, layer) => {
		const node = e.target;
		const scaleX = node.scaleX();
		const scaleY = node.scaleY();
		node.scaleX(1);
		node.scaleY(1);

		const changes = {
			x: node.x(),
			y: node.y(),
			rotation: node.rotation(),
		};

		if (layer.type === 'text' && !layer.isCurved && (layer.warpStyle === 'none' || !layer.warpStyle)) {
			changes.fontSize = Math.round((layer.fontSize || 24) * scaleY);
			changes.width = node.width() * scaleX;
		} else {
			changes.width = node.width() * scaleX;
			changes.height = node.height() * scaleY;
		}
		updateLayer(layer.id, changes);
	};

	const handleLayerSelect = (e, layerId) => {
		// Stop propagation to prevent stage click
		e.cancelBubble = true;

		if (e.evt.shiftKey || e.evt.ctrlKey || e.evt.metaKey) {
			toggleLayerSelection(layerId);
		} else {
			setSelectedLayerId(layerId);
		}
	};

	// --- TEXT EDITING ---
	const [editingLayerId, setEditingLayerId] = useState(null);
	const [textAreaPos, setTextAreaPos] = useState({ x: 0, y: 0 });
	const [textAreaValue, setTextAreaValue] = useState('');
	const textAreaRef = useRef(null);

	const handleTextDblClick = (e, layer) => {
		e.cancelBubble = true;
		const node = e.target;
		const textPosition = node.getAbsolutePosition();

		// Textarea is absolute inside the relative canvas-wrapper, 
		// so we just need the position relative to the stage container.
		const areaPosition = {
			x: textPosition.x,
			y: textPosition.y,
		};
		setTextAreaPos(areaPosition);
		setTextAreaValue(layer.label || '');
		setEditingLayerId(layer.id);
	};

	const handleTextAreaChange = (e) => {
		setTextAreaValue(e.target.value);
		// Live update on canvas too? Maybe confusing if not commited, but standard behavior is usually commit on blur/enter.
		// For now we commit on Blur/Enter to keep history clean.
		// Actually let's live update for "Feel".
		// updateLayer(editingLayerId, { label: e.target.value }); // Too many updates?
	};

	const handleTextAreaKeyDown = (e) => {
		if (e.key === 'Enter' && !e.shiftKey) {
			updateLayer(editingLayerId, { label: textAreaValue });
			setEditingLayerId(null);
		}
		if (e.key === 'Escape') {
			setEditingLayerId(null);
		}
	};

	const handleTextAreaBlur = () => {
		if (editingLayerId) {
			updateLayer(editingLayerId, { label: textAreaValue });
			setEditingLayerId(null);
		}
	};

	// --- RENDERERS ---


	// --- RENDERERS ---
	// Removed renderTextContent as it is now handled by DesignRenderer

	return (
		<div
			ref={containerRef}
			className="canvas-wrapper"
			style={{
				width: '100%',
				height: '100%',
				position: 'relative',
				display: 'flex',
				flexDirection: 'column',
				background: 'transparent',
				overflow: 'hidden',
			}}
		>
			<Ruler orientation="horizontal" scale={stageScale} offset={stagePos.x} length={containerSize.width} />
			<Ruler orientation="vertical" scale={stageScale} offset={stagePos.y} length={containerSize.height} />

			<Stage
				width={containerSize.width}
				height={containerSize.height}
				onMouseDown={checkDeselect}
				onTouchStart={checkDeselect}
				onWheel={handleWheel}
				scaleX={stageScale}
				scaleY={stageScale}
				x={stagePos.x}
				y={stagePos.y}
				ref={stageRef}
				style={{ position: 'absolute', top: 0, left: 0 }}
			>
				<Layer>
					<Rect width={settings.canvasWidth} height={stageHeight} fill="#fff" shadowBlur={10} shadowColor="rgba(0,0,0,0.1)" listening={false} />

					{/* Grid Lines */}
					{showGrid && (
						<Group listening={false}>
							{[...Array(Math.ceil(settings.canvasWidth / gridSize))].map((_, i) => (
								<Line
									key={`v - ${i} `}
									points={[i * gridSize, 0, i * gridSize, stageHeight]}
									stroke="#ddd"
									strokeWidth={1}
								/>
							))}
							{[...Array(Math.ceil(stageHeight / gridSize))].map((_, i) => (
								<Line
									key={`h - ${i} `}
									points={[0, i * gridSize, settings.canvasWidth, i * gridSize]}
									stroke="#ddd"
									strokeWidth={1}
								/>
							))}
						</Group>
					)}

					{/* Shared Renderer */}
					<DesignRenderer
						layers={layers}
						// Admin doesn't use input/style maps for live editing usually, it uses the layers array directly?
						// Wait, updateLayer updates the 'layers' store. 
						// So inputs/styles are null. The Loop uses layer.property directly.
						// DesignRenderer handles this via `layer.label` fallbacks.

						personalisationMethod={personalisationMethod}
						embroideryColor={null} // Global color not used in Admin? Or should be? Admin edits template, not user instance.

						backgroundImage={backgroundImage}
						overlayImage={overlayImage}

						width={settings.canvasWidth}
						height={stageHeight}

						onLayerClick={handleLayerSelect}
						onLayerDragStart={() => setGuides([])}
						onLayerDragMove={handleDragMove}
						onLayerDragEnd={handleDragEnd}
						onLayerTransformEnd={handleTransformEnd}
						onTextDblClick={handleTextDblClick}

						selectedLayerIds={selectedLayerIds}
						editingLayerId={editingLayerId}
					>
						<Transformer ref={trRef} />
					</DesignRenderer>
				</Layer>
			</Stage>

			{/* Text Area Overlay */}
			{editingLayerId && (
				<textarea
					ref={textAreaRef}
					value={textAreaValue}
					onChange={handleTextAreaChange}
					onKeyDown={handleTextAreaKeyDown}
					onBlur={handleTextAreaBlur}
					style={{
						position: 'absolute',
						top: textAreaPos.y || 0,
						left: textAreaPos.x || 0,
						width: '200px', // Dynamically size this?
						height: '100px',
						border: '1px solid #2271b1',
						padding: '4px',
						margin: '0',
						background: 'rgba(255,255,255,0.9)',
						fontSize: '14px',
						zIndex: 9999,
						resize: 'both' // Allow user to resize
					}}
					autoFocus
				/>
			)}
		</div>
	);
});

export default CanvasStage;
