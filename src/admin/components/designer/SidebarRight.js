import { useState, Fragment, useEffect } from '@wordpress/element';
import {
	Button,
	TextControl,
	Tooltip,
} from '@wordpress/components';
import {
	DndContext,
	closestCenter,
	KeyboardSensor,
	PointerSensor,
	useSensor,
	useSensors,
} from '@dnd-kit/core';
import {
	arrayMove,
	SortableContext,
	sortableKeyboardCoordinates,
	verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import useStore from '../../store/useStore';
import PropertiesPanel from './PropertiesPanel';
import LayerListItem from './LayerListItem';

/**
 * SidebarRight component for layer management in the designer.
 * Uses dnd-kit for drag-and-drop layer reordering.
 */
const SidebarRight = ({ fonts, addNotice, setIsLoading }) => {
	const selectedLayerId = useStore((state) => state.selectedLayerId);
	const setSelectedLayerId = useStore(
		(state) => state.setSelectedLayerId
	);
	const addLayer = useStore((state) => state.addLayer);
	const updateLayer = useStore((state) => state.updateLayer);
	const removeLayer = useStore((state) => state.removeLayer);
	const reorderLayers = useStore((state) => state.reorderLayers);
	const duplicateLayer = useStore((state) => state.duplicateLayer);
	const flipLayer = useStore((state) => state.flipLayer);

	// View State needed to get current layers
	const views = useStore((state) => state.views);
	const currentViewId = useStore((state) => state.currentViewId);

	const currentView = views.find((v) => v.id === currentViewId);
	const layers = currentView ? currentView.layers : [];
	const selectedLayer = layers.find((l) => l.id === selectedLayerId);

	const [allCategories, setAllCategories] = useState([]);

	useEffect(() => {
		// Fetch categories for clipart assets
		apiFetch({ path: '/personaliseit/v1/assets' })
			.then((data) => {
				if (data && typeof data === 'object') {
					setAllCategories(Object.keys(data));
				}
			})
			.catch(err => console.error(err));
	}, []);

	const personalisationMethod = useStore((state) => state.personalisationMethod);

	// dnd-kit sensors for pointer and keyboard accessibility
	const sensors = useSensors(
		useSensor(PointerSensor),
		useSensor(KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		})
	);

	/**
	 * Handle drag end event from dnd-kit.
	 * Converts reversed display indices to actual array indices for reordering.
	 */
	const handleDragEnd = (event) => {
		const { active, over } = event;
		if (active.id !== over?.id) {
			const oldIndex = layersReversed.findIndex((l) => l.id === active.id);
			const newIndex = layersReversed.findIndex((l) => l.id === over.id);
			// Convert from reversed display index to actual array index
			const actualOldIndex = layersSafe.length - 1 - oldIndex;
			const actualNewIndex = layersSafe.length - 1 - newIndex;
			reorderLayers(actualOldIndex, actualNewIndex);
		}
	};

	const handleSelectLayer = (id) => {
		setSelectedLayerId(id);
	};

	const handleAddClipartClick = () => {
		addLayer({
			id: `layer_${Date.now()}`,
			type: 'clipart',
			label: __('Clipart Zone', 'personaliseit'),
			x: 100, y: 100, width: 150, height: 150,
			allowedCategories: [], scaleX: 1, scaleY: 1, rotation: 0,
		});
	};

	// Use layers directly
	const layersSafe = Array.isArray(layers) ? layers : [];
	// Reversed for display (top layer first)
	const layersReversed = layersSafe.slice().reverse();

	return (
		<div className="personaliseit-designer__right-panel">
			{/* Top Half: Layer Management (Scrolls) */}
			<div className="personaliseit-designer__layers">
				{/* Tools Header */}
				<div className="personaliseit-designer__layers-tools">
					<div className="personaliseit-designer__tools-row" style={{ justifyContent: 'space-between' }}>
						<Tooltip text={__('Add Text', 'personaliseit')}>
							<Button
								variant="secondary"
								isSmall
								icon="editor-textcolor"
								className="personaliseit-designer__tool-btn"
								onClick={() =>
									addLayer({
										id: `layer_${Date.now()}`,
										type: 'text',
										label: __('New Text', 'personaliseit'),
										x: 100, y: 100, width: 300, height: 50,
										textTransform: 'none', align: 'center', fontSize: 32,
										fontFamily: fonts.length > 0 ? fonts[0].family : 'Arial',
										color: '#000000', scaleX: 1, scaleY: 1, rotation: 0, lineHeight: 1.2,
									})
								}
								label={__('Add Text', 'personaliseit')}
							/>
						</Tooltip>

						<Tooltip text={__('Add Image', 'personaliseit')}>
							<Button
								variant="secondary"
								isSmall
								icon="format-image"
								className="personaliseit-designer__tool-btn"
								onClick={() => document.getElementById('layer-image-upload').click()}
								label={__('Add Image', 'personaliseit')}
							/>
						</Tooltip>
						<input
							type="file"
							id="layer-image-upload"
							style={{ display: 'none' }}
							accept="image/*"
							onChange={async (e) => {
								const file = e.target.files[0];
								if (!file) return;
								const formData = new FormData();
								formData.append('file', file);
								try {
									setIsLoading(true);
									const response = await apiFetch({ path: '/wp/v2/media', method: 'POST', body: formData });
									addLayer({
										id: `layer_${Date.now()}`,
										type: 'image',
										label: __('Image', 'personaliseit'),
										x: 100, y: 100, width: 200, height: 200,
										image: response.source_url,
										maskImage: null, scaleX: 1, scaleY: 1, rotation: 0,
									});
								} catch (err) { console.error(err); }
								finally { setIsLoading(false); }
							}}
						/>
						<Tooltip text={__('Add Clipart', 'personaliseit')}>
							<Button
								variant="secondary"
								isSmall
								icon="images-alt2"
								className="personaliseit-designer__tool-btn"
								onClick={handleAddClipartClick}
								label={__('Add Clipart', 'personaliseit')}
							/>
						</Tooltip>
						<Tooltip text={__('Add Spotify Code', 'personaliseit')}>
							<Button
								variant="secondary"
								isSmall
								icon="audio"
								className="personaliseit-designer__tool-btn"
								onClick={() => {
									const uri = prompt(__('Enter Spotify URI or URL (e.g., spotify:track:... or open.spotify.com/track/...)', 'personaliseit'));
									if (uri) {
										// Default colors for the Spotify code - transparent background
										const bgColor = '000000';
										const barColor = 'ffffff'; // White bars on black background
										addLayer({
											id: `layer_${Date.now()}`,
											type: 'spotify',
											label: __('Spotify Code', 'personaliseit'),
											x: 100, y: 100, width: 320, height: 80,
											// Build URL with color parameters
											image: `/wp-json/personaliseit/v1/spotify/code?raw=1&format=svg&bg=${bgColor}&color=${barColor}&uri=${encodeURIComponent(uri)}`,
											spotifyUri: uri,
											spotifyBarColor: `#${barColor}`,
											spotifyBgColor: `#${bgColor}`,
											spotifyShowTitle: false,
											spotifyShowArtist: false,
											scaleX: 1, scaleY: 1, rotation: 0,
										});
									}
								}}
								label={__('Add Spotify Code', 'personaliseit')}
							/>
						</Tooltip>
					</div>
				</div>

				<div className="personaliseit-designer__layers-list-scroll">
					<DndContext
						sensors={sensors}
						collisionDetection={closestCenter}
						onDragEnd={handleDragEnd}
					>
						<SortableContext
							items={layersReversed.map((l) => l.id)}
							strategy={verticalListSortingStrategy}
						>
							<ul className="personaliseit-designer__layers-list-ul">
								{layersReversed.length === 0 ? (
									<div className="personaliseit-designer__layers-empty" style={{ padding: '20px', textAlign: 'center', color: '#999', fontSize: 12 }}>
										{__('No layers added', 'personaliseit')}
									</div>
								) : (
									layersReversed.map((layer, index) => (
										<LayerListItem
											key={layer.id}
											layer={layer}
											selectedId={selectedLayerId}
											index={index}
											onSelect={handleSelectLayer}
											onDuplicate={duplicateLayer}
											onDelete={removeLayer}
											onUpdate={updateLayer}
											onFlip={flipLayer}
										/>
									))
								)}
							</ul>
						</SortableContext>
					</DndContext>
				</div>
			</div>

			{/* Properties Section */}
			<PropertiesPanel
				selectedLayer={selectedLayer}
				updateLayer={updateLayer}
				removeLayer={removeLayer}
				fonts={fonts}
				personalisationMethod={personalisationMethod}
				allCategories={allCategories}
				setIsLoading={setIsLoading}
			/>
		</div>
	);
};

export default SidebarRight;
