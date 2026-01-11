import { useState, useEffect, useRef } from '@wordpress/element';
import { Spinner, SnackbarList, Button } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import useStore from '../store/useStore';
import CanvasStage from './CanvasStage';
import SidebarLeft from './designer/SidebarLeft';
import SidebarRight from './designer/SidebarRight';
import SaveTemplateModal from './modals/SaveTemplateModal';
import LoadTemplateModal from './modals/LoadTemplateModal';
import FontService from '../../common/services/FontService';

const Designer = () => {
	const selectedProduct = useStore((state) => state.selectedProduct);
	const setSelectedProduct = useStore((state) => state.setSelectedProduct);
	const orderMode = useStore((state) => state.orderMode);
	const templateId = useStore((state) => state.templateId); // Get templateId from store

	// View State (needed for Saving)
	const views = useStore((state) => state.views);
	const setViews = useStore((state) => state.setViews);
	const setCurrentViewId = useStore((state) => state.setCurrentViewId);

	// Variation State
	const setVariations = useStore((state) => state.setVariations);
	const currentVariationId = useStore(
		(state) => state.currentVariationId
	);
	const setCurrentVariationId = useStore(
		(state) => state.setCurrentVariationId
	);

	const personalisationMethod = useStore((state) => state.personalisationMethod);
	const setPersonalisationMethod = useStore((state) => state.setPersonalisationMethod);
	const paletteMap = useStore((state) => state.paletteMap);
	const setPaletteMap = useStore((state) => state.setPaletteMap);
	const exportConfig = useStore((state) => state.exportConfig);
	const importConfig = useStore((state) => state.importConfig);
	const fetchSettings = useStore((state) => state.fetchSettings);

	// Layer Actions (Undo/Redo only needed here for keyboard shortcuts, but let's keep them handy)
	const selectedLayerId = useStore((state) => state.selectedLayerId);
	const duplicateLayer = useStore((state) => state.duplicateLayer);
	const removeLayer = useStore((state) => state.removeLayer);
	const undo = useStore((state) => state.undo);
	const redo = useStore((state) => state.redo);

	const canvasRef = useRef();

	const [isSaving, setIsSaving] = useState(false);
	const [isLoading, setIsLoading] = useState(false);
	const [notices, setNotices] = useState([]);
	const [fonts, setFonts] = useState([]);

	// Template State
	const [isSaveModalOpen, setIsSaveModalOpen] = useState(false);
	const [isLoadModalOpen, setIsLoadModalOpen] = useState(false);

	// UI Tools
	const [showGrid, setShowGrid] = useState(false);
	const [snapToGrid, setSnapToGrid] = useState(false);

	const addNotice = (message, status = 'success') => {
		const id = Date.now();
		setNotices((prev) => [...prev, { id, content: message, status }]);
		setTimeout(() => removeNotice(id), 3000);
	};

	const removeNotice = (id) => {
		setNotices((prev) => prev.filter((notice) => notice.id !== id));
	};

	// Keyboard Shortcuts
	useEffect(() => {
		const handleKeyDown = (e) => {
			// Ignore if input/textarea is focused
			if (
				['INPUT', 'TEXTAREA', 'SELECT'].includes(
					e.target.tagName
				) ||
				e.target.isContentEditable
			) {
				return;
			}

			// Undo: Ctrl+Z
			if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
				e.preventDefault();
				undo();
				return;
			}

			// Redo: Ctrl+Shift+Z or Ctrl+Y
			if (
				((e.ctrlKey || e.metaKey) &&
					e.shiftKey &&
					e.key.toLowerCase() === 'z') ||
				((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y')
			) {
				e.preventDefault();
				redo();
				return;
			}

			// Duplicate: Ctrl+D
			if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'd') {
				e.preventDefault();
				if (selectedLayerId) duplicateLayer(selectedLayerId);
				return;
			}

			// Delete: Delete or Backspace
			if (e.key === 'Delete' || e.key === 'Backspace') {
				e.preventDefault();
				if (selectedLayerId) removeLayer(selectedLayerId);
				return;
			}
		};

		window.addEventListener('keydown', handleKeyDown);
		return () => window.removeEventListener('keydown', handleKeyDown);
	}, [selectedLayerId, undo, redo, duplicateLayer, removeLayer]);

	useEffect(() => {
		fetchSettings();
	}, []);

	useEffect(() => {
		if (orderMode) {
			loadOrderData();
		} else if (templateId) {
			loadTemplateData();
		} else if (selectedProduct) {
			loadConfig();
		}

		// Fetch fonts
		FontService.getFonts().then((data) => {
			setFonts(data);
			FontService.loadFontsIntoDom(data);
		}).catch(console.error);
	}, [selectedProduct, orderMode]);

	const loadOrderData = async () => {
		setIsLoading(true);
		try {
			const data = await apiFetch({
				path: `/personaliseit/v1/order-item/${orderMode.orderId}/${orderMode.itemId}`,
			});

			if (!selectedProduct) {
				setSelectedProduct({
					id: data.product_id,
					name: `Order #${orderMode.orderId}`,
				});
			}

			if (data.config) {
				const config = data.config;
				const userInputs = data.userInputs || {};
				const inputs = userInputs.inputs || userInputs;
				const styles = userInputs.styles || {};
				const customLayers = userInputs.customLayers || [];

				if (config.views) {
					config.views.forEach((v) => {
						v.layers.forEach((l) => {
							if (inputs[l.id]) {
								if (l.type === 'text') l.text = inputs[l.id];
								if (l.type === 'image') l.image = inputs[l.id];
							}
							if (styles[l.id]) {
								Object.assign(l, styles[l.id]);
							}
						});
					});
					// Add Custom Layers to first view (simplification)
					if (customLayers.length > 0 && config.views.length > 0) {
						config.views[0].layers.push(...customLayers);
					}
				}
				importConfig(config);
			}
		} catch (error) {
			console.error(error);
			addNotice(
				__('Failed to load order data.', 'personaliseit'),
				'error'
			);
		} finally {
			setIsLoading(false);
		}
	};

	const loadTemplateData = async () => {
		setIsLoading(true);
		try {
			const post = await apiFetch({ path: `/wp/v2/personaliseit_tpl/${templateId}?context=edit` });
			let config;
			try {
				config = JSON.parse(post.content.raw);
			} catch (e) {
				config = post.content.raw;
			}
			if (typeof config === 'string') {
				try { config = JSON.parse(config); } catch (e) { /* ignore */ }
			}

			if (config) {
				importConfig(config);
			} else {
				addNotice(__('Template is empty.', 'personaliseit'), 'warning');
			}
		} catch (error) {
			console.error(error);
			addNotice(__('Failed to load template.', 'personaliseit'), 'error');
		} finally {
			setIsLoading(false);
		}
	};

	const loadConfig = async () => {
		setIsLoading(true);
		try {
			const product = await apiFetch({
				path: `/wc/v3/products/${selectedProduct.id}`,
			});

			// Load variations if variable product
			if (product.type === 'variable') {
				const variationData = await apiFetch({
					path: `/wc/v3/products/${selectedProduct.id}/variations`,
				});
				const vars = variationData.map((v) => ({
					id: v.id,
					name:
						v.attributes.map((a) => a.option).join(' - ') ||
						`Variation #${v.id}`,
					image: v.image ? v.image.src : '',
				}));
				const uniqueVars = [];
				const map = new Map();
				for (const item of vars) {
					if (!map.has(item.id)) {
						map.set(item.id, true);
						uniqueVars.push(item);
					}
				}
				setVariations(uniqueVars);
				if (uniqueVars.length > 0 && !currentVariationId) {
					setCurrentVariationId(uniqueVars[0].id);
				}
			} else {
				setVariations([]);
				setCurrentVariationId(null);
			}

			// Load existing meta config
			const meta = product.meta_data.find(
				(m) => m.key === '_personaliseit_config'
			);
			if (meta && meta.value) {
				if (meta.value.personalisationMethod) setPersonalisationMethod(meta.value.personalisationMethod);
				else setPersonalisationMethod('none');

				if (meta.value.paletteMap) setPaletteMap(meta.value.paletteMap);
				else setPaletteMap({});

				let loadedViews = meta.value.views || [];
				if (loadedViews.length === 0) {
					loadedViews = [
						{
							id: 'view_default',
							name: 'Front',
							image: product.images[0]?.src || '',
							layers: [],
							variationImages: {},
						},
					];
				}
				setViews(loadedViews);
				setCurrentViewId(loadedViews[0].id);
			} else {
				setPersonalisationMethod('none');
				const initialView = {
					id: 'view_default',
					name: 'Front',
					image: product.images[0]?.src || '',
					layers: [],
					variationImages: {},
				};
				setViews([initialView]);
				setCurrentViewId(initialView.id);
			}
		} catch (error) {
			console.error(error);
			addNotice(
				__('Failed to load configuration.', 'personaliseit'),
				'error'
			);
		} finally {
			setIsLoading(false);
		}
	};

	const handleSave = async () => {
		setIsSaving(true);
		try {
			const dataToSave = {
				views: views,
				personalisationMethod: personalisationMethod,
				paletteMap: paletteMap
			};

			if (templateId) {
				// Save to Template Custom Post Type
				await apiFetch({
					path: `/wp/v2/personaliseit_tpl/${templateId}`,
					method: 'POST',
					data: {
						content: JSON.stringify(dataToSave)
					}
				});
			} else {
				// Save to Product Meta
				await apiFetch({
					path: `/wc/v3/products/${selectedProduct.id}`,
					method: 'PUT',
					data: {
						meta_data: [
							{
								key: '_personaliseit_config',
								value: dataToSave,
							},
						],
					},
				});
			}

			addNotice(
				__('Configuration saved successfully!', 'personaliseit'),
				'success'
			);
		} catch (error) {
			console.error(error);
			addNotice(
				__('Failed to save configuration.', 'personaliseit'),
				'error'
			);
		} finally {
			setIsSaving(false);
		}
	};

	// Mobile View State
	const [mobileView, setMobileView] = useState('canvas'); // 'left', 'canvas', 'right'

	return (
		<div className={`personaliseit-designer-container personaliseit-mobile-view-${mobileView}`}>
			{/* Main Workspace */}
			<div className="personaliseit-designer__workspace">
				{/* Left Sidebar */}
				<div className={`personaliseit-designer__sidebar personaliseit-designer__sidebar--left ${mobileView === 'left' ? 'mobile-visible' : ''}`}>
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

				{/* Center Canvas */}
				<div className={`personaliseit-designer__canvas ${mobileView === 'canvas' ? 'mobile-visible' : ''}`}>
					{isLoading && (
						<div className="absolute-center-loader" style={{ position: 'absolute', zIndex: 100 }}>
							<Spinner />
						</div>
					)}

					<CanvasStage ref={canvasRef} showGrid={showGrid} snapToGrid={snapToGrid} />

					{/* Canvas Toolbar overlay */}
					<div className="personaliseit-designer__toolbar">
						<Button
							icon="minus"
							onClick={() => canvasRef.current?.zoomOut()}
							label={__('Zoom Out', 'personaliseit')}
						/>
						<Button
							icon="plus"
							onClick={() => canvasRef.current?.zoomIn()}
							label={__('Zoom In', 'personaliseit')}
						/>
						<Button
							icon="fullscreen"
							onClick={() => canvasRef.current?.fitToScreen()}
							label={__('Fit to Screen', 'personaliseit')}
						/>
						<div style={{ width: 1, height: 20, background: '#ccc', margin: '0 5px' }}></div>
						<Button
							icon="grid-view"
							isPressed={showGrid}
							onClick={() => setShowGrid(!showGrid)}
							label={__('Toggle Grid', 'personaliseit')}
						/>
						<Button
							icon="align-center"
							isPressed={snapToGrid}
							onClick={() => setSnapToGrid(!snapToGrid)}
							label={__('Snap to Grid', 'personaliseit')}
						/>
					</div>
				</div>

				{/* Right Sidebar */}
				<div className={`personaliseit-designer__sidebar personaliseit-designer__sidebar--right ${mobileView === 'right' ? 'mobile-visible' : ''}`}>
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
									onClick={() => {
										if (canvasRef.current) {
											const stage = canvasRef.current.getStage();
											// Hide selection/transformer
											const tr = stage.findOne('Transformer');
											if (tr) tr.hide();

											// Hide Background/Overlay
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

											if (tr) tr.show();
											if (bg) bg.show();
											if (overlay) overlay.show();
										}
									}}
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
				<button
					className={`mobile-nav-item ${mobileView === 'left' ? 'active' : ''}`}
					onClick={() => setMobileView('left')}
				>
					<span className="dashicons dashicons-admin-settings"></span>
					<span>{__('Config', 'personaliseit')}</span>
				</button>
				<button
					className={`mobile-nav-item ${mobileView === 'canvas' ? 'active' : ''}`}
					onClick={() => setMobileView('canvas')}
				>
					<span className="dashicons dashicons-art"></span>
					<span>{__('Canvas', 'personaliseit')}</span>
				</button>
				<button
					className={`mobile-nav-item ${mobileView === 'right' ? 'active' : ''}`}
					onClick={() => setMobileView('right')}
				>
					<span className="dashicons dashicons-layers"></span>
					<span>{__('Layers', 'personaliseit')}</span>
				</button>
			</div>

			<div className="designer-notices" style={{ position: 'fixed', bottom: 20, right: 20, zIndex: 1000 }}>
				<SnackbarList notices={notices} onRemove={removeNotice} />
			</div>

			{/* Save Template Modal */}
			<SaveTemplateModal
				isOpen={isSaveModalOpen}
				onClose={() => setIsSaveModalOpen(false)}
				addNotice={addNotice}
				exportConfig={exportConfig}
				captureSnapshot={() => {
					if (canvasRef.current) {
						const stage = canvasRef.current.getStage();
						if (stage) {
							// Return data URL, small size for thumbnail
							// Hide transformer temporarily? 
							// The transformer is on top, might look ugly. 
							// Better: find transformer and hide it.
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

			{/* Load Template Modal */}
			<LoadTemplateModal
				isOpen={isLoadModalOpen}
				onClose={() => setIsLoadModalOpen(false)}
				addNotice={addNotice}
				importConfig={importConfig}
			/>
		</div>
	);
};

export default Designer;
