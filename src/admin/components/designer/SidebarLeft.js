import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	ToggleControl,
	SelectControl,
	Button,
	TextControl,
	FormFileUpload,
	Popover,
	Tooltip,
	RangeControl,
	Spinner
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import useStore from '../../store/useStore';

const SidebarLeft = ({
	selectedProduct,
}) => {
	const views = useStore((state) => state.views);
	const currentViewId = useStore((state) => state.currentViewId);
	const setCurrentViewId = useStore((state) => state.setCurrentViewId);
	const addView = useStore((state) => state.addView);
	const removeView = useStore((state) => state.removeView);
	const updateView = useStore((state) => state.updateView);
	const settings = useStore((state) => state.settings);
	const personalisationMethod = useStore((state) => state.personalisationMethod);
	const setPersonalisationMethod = useStore((state) => state.setPersonalisationMethod);
	const paletteMap = useStore((state) => state.paletteMap);
	const setPaletteForMethod = useStore((state) => state.setPaletteForMethod);

	const variations = useStore((state) => state.variations);
	const currentVariationId = useStore(
		(state) => state.currentVariationId
	);
	const setCurrentVariationId = useStore(
		(state) => state.setCurrentVariationId
	);

	const [isUploading, setIsUploading] = useState(false);
	const [availablePalettes, setAvailablePalettes] = useState([]);

	useEffect(() => {
		apiFetch({ path: '/personaliseit/v1/palettes' }).then(setAvailablePalettes).catch(console.error);
	}, []);

	const currentView = views.find((v) => v.id === currentViewId);

	const handleBackgroundUpload = async (event) => {
		const file = event.target.files[0];
		if (!file) return;

		setIsUploading(true);
		const formData = new FormData();
		formData.append('file', file);
		try {
			const response = await apiFetch({
				path: '/wp/v2/media',
				method: 'POST',
				body: formData,
			});
			const imageUrl = response.source_url;

			if (currentVariationId && currentView) {
				// Update variation specific image
				const newVarImages = {
					...currentView.variationImages,
					[currentVariationId]: imageUrl,
				};
				updateView(currentViewId, { variationImages: newVarImages });
			} else {
				// Update base view image
				updateView(currentViewId, { image: imageUrl });
			}
		} catch (error) {
			console.error(error);
			// Optional: Add notice here
		} finally {
			setIsUploading(false);
		}
	};





	return (
		<div className="personaliseit-designer__left-panel">
			<div className="personaliseit-designer__content-inner">

				{settings && settings.enabledMethods && (
					<div className="personaliseit-designer__section">
						<h3>{__('Method', 'personaliseit')}</h3>
						<SelectControl
							label={__('Personalisation Type', 'personaliseit')}
							value={personalisationMethod}
							options={[
								{ label: __('None / Standard', 'personaliseit'), value: 'none' },
								...(settings.enabledMethods.engraving ? [{ label: 'Engraving', value: 'engraving' }] : []),
								...(settings.enabledMethods.embroidery ? [{ label: 'Embroidery', value: 'embroidery' }] : []),
								...(settings.enabledMethods.dtf ? [{ label: 'DTF Printing', value: 'dtf' }] : []),
								...(settings.enabledMethods.uv ? [{ label: 'UV Printing', value: 'uv' }] : []),
								...(settings.enabledMethods.sublimation ? [{ label: 'Sublimation', value: 'sublimation' }] : []),
							]}
							onChange={setPersonalisationMethod}
							help={__('Select the production method for this product.', 'personaliseit')}
						/>

						{personalisationMethod !== 'none' && (
							<div style={{ marginTop: '15px' }}>
								<SelectControl
									label={__('Color Palette', 'personaliseit')}
									value={paletteMap[personalisationMethod] || ''}
									options={[
										{ label: __('Default (All Colors)', 'personaliseit'), value: '' },
										...availablePalettes.map(p => ({ label: p.title, value: p.id }))
									]}
									onChange={(val) => setPaletteForMethod(personalisationMethod, val)}
									help={__('Restrict the colors available for this method.', 'personaliseit')}
								/>
							</div>
						)}
					</div>
				)}

				{ /* Variations Section */}
				{variations.length > 0 && (
					<div className="personaliseit-designer__section">
						<h3>{__('Variations', 'personaliseit')}</h3>
						<ul className="personaliseit-designer__variation-list">
							{variations.map((variation) => (
								<li
									key={variation.id}
									className={currentVariationId === variation.id ? 'active' : ''}
									onClick={() => setCurrentVariationId(variation.id)}
								>
									{variation.name}
								</li>
							))}
						</ul>
					</div>
				)}

				{ /* Views Section */}
				<div className="personaliseit-designer__section">
					<div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' }}>
						<h3>{__('Views', 'personaliseit')}</h3>
						<div>
							<Button
								isSecondary
								isSmall
								icon="plus"
								label={__('Add Empty View', 'personaliseit')}
								onClick={() =>
									addView({
										id: `view_${Date.now()}`,
										name: `View ${views.length + 1}`,
										image: selectedProduct.images[0]?.src || '',
										layers: [],
										variationImages: {},
									})
								}
								style={{ marginRight: 5 }}
							/>
							{views.length > 0 && (
								<Button
									isSecondary
									isSmall
									icon="admin-page"
									label={__('Duplicate Current', 'personaliseit')}
									onClick={() => {
										// Deep duplicate current view
										const current = views.find(v => v.id === currentViewId) || views[0];
										const newView = {
											...current,
											id: `view_${Date.now()}`,
											name: `${current.name} (Copy)`,
											// layers need deep copy to avoid reference issues
											layers: current.layers.map(l => ({ ...l, id: `layer_${Date.now()}_${Math.random().toString(36).substr(2, 5)}` }))
										};
										addView(newView);
									}}
								/>
							)}
						</div>
					</div>

					<div className="personaliseit-designer__view-list">
						{views.map((view) => (
							<div
								key={view.id}
								className={`personaliseit-designer__view-item ${currentViewId === view.id ? 'active' : ''}`}
								onClick={() => setCurrentViewId(view.id)}
							>
								<span>{view.name}</span>
								{views.length > 1 && (
									<Button
										icon="trash"
										isSmall
										isDestructive
										className="personaliseit-designer__view-delete-btn"
										onClick={(e) => {
											e.stopPropagation();
											removeView(view.id);
										}}
										label={__('Delete View', 'personaliseit')}
									/>
								)}
							</div>
						))}
					</div>

					{ /* Current View Properties */}
					{currentView && (
						<div className="personaliseit-designer__view-settings">
							<h4>{__('View Settings', 'personaliseit')}</h4>

							<TextControl
								label={__('View Name', 'personaliseit')}
								value={currentView.name}
								onChange={(val) => updateView(currentView.id, { name: val })}
								style={{ marginBottom: '15px' }}
							/>

							<label style={{ display: 'block', marginBottom: '8px', fontSize: '12px' }}>
								{__('Background Image', 'personaliseit')}
							</label>

							{currentVariationId && (
								<p style={{ fontSize: '11px', color: '#666', fontStyle: 'italic', marginBottom: '8px' }}>
									{__('Editing background for selected variation.', 'personaliseit')}
								</p>
							)}

							<div className="personaliseit-designer__upload-area">
								{isUploading ? (
									<Spinner />
								) : (
									<FormFileUpload
										accept="image/*"
										onChange={handleBackgroundUpload}
										render={({ openFileDialog }) => (
											<Button isSecondary isSmall onClick={openFileDialog}>
												{__('Change Image', 'personaliseit')}
											</Button>
										)}
									/>
								)}
							</div>

							<label style={{ display: 'block', marginBottom: '8px', fontSize: '12px', marginTop: '15px' }}>
								{__('Thumbnail (Frontend Swatch)', 'personaliseit')}
							</label>
							{currentView.thumbnail ? (
								<div style={{ marginBottom: 10 }}>
									<div style={{ position: 'relative', width: '60px', marginBottom: 5 }}>
										<img src={currentView.thumbnail} alt="Thumbnail" style={{ width: '100%', border: '1px solid #ddd' }} />
										<Button
											icon="no"
											label={__('Remove Thumbnail', 'personaliseit')}
											onClick={() => updateView(currentView.id, { thumbnail: null })}
											isSmall
											isDestructive
											style={{ position: 'absolute', top: -5, right: -5, padding: 0, minWidth: 20, height: 20 }}
										/>
									</div>
								</div>
							) : (
								<div className="personaliseit-designer__upload-area">
									<FormFileUpload
										accept="image/*"
										onChange={async (e) => {
											const file = e.target.files[0];
											if (!file) return;
											setIsUploading(true);
											const formData = new FormData();
											formData.append('file', file);
											try {
												const response = await apiFetch({ path: '/wp/v2/media', method: 'POST', body: formData });
												updateView(currentView.id, { thumbnail: response.source_url });
											} catch (err) { console.error(err); }
											setIsUploading(false);
										}}
										render={({ openFileDialog }) => (
											<Button isSecondary isSmall onClick={openFileDialog}>
												{__('Upload Thumbnail', 'personaliseit')}
											</Button>
										)}
									/>
								</div>
							)}

							<div className="section-divider mt-3 pt-3" style={{ marginTop: '15px', paddingTop: '15px', borderTop: '1px solid #eee' }}>
								<label style={{ display: 'block', marginBottom: '8px', fontSize: '12px' }}>
									{__('Displacement Map (Advanced)', 'personaliseit')}
									<Tooltip text={__('Upload a grayscale map to distort the design. 50% gray is neutral. Dark/Light pixels shift pixels left/right/up/down.', 'personaliseit')}>
										<span className="dashicons dashicons-info" style={{ fontSize: 14, color: '#999', marginLeft: 5, verticalAlign: 'middle' }}></span>
									</Tooltip>
								</label>

								{currentView.displacementImage ? (
									<div style={{ marginBottom: 10 }}>
										<div style={{ position: 'relative', width: '100px', marginBottom: 5 }}>
											<img src={currentView.displacementImage} alt="Displacement Map" style={{ width: '100%', border: '1px solid #ddd' }} />
											<Button
												icon="no"
												label={__('Remove Map', 'personaliseit')}
												onClick={() => updateView(currentView.id, { displacementImage: null })}
												isSmall
												isDestructive
												style={{ position: 'absolute', top: -5, right: -5, padding: 0, minWidth: 20, height: 20 }}
											/>
										</div>
										<RangeControl
											label={__('Intensity', 'personaliseit')}
											value={currentView.displacementScale || 20}
											onChange={(val) => updateView(currentView.id, { displacementScale: val })}
											min={0}
											max={100}
										/>
									</div>
								) : (
									<div className="personaliseit-designer__upload-area">
										<FormFileUpload
											accept="image/*"
											onChange={async (e) => {
												const file = e.target.files[0];
												if (!file) return;
												setIsUploading(true);
												const formData = new FormData();
												formData.append('file', file);
												try {
													const response = await apiFetch({ path: '/wp/v2/media', method: 'POST', body: formData });
													updateView(currentView.id, { displacementImage: response.source_url, displacementScale: 20 });
												} catch (err) { console.error(err); }
												setIsUploading(false);
											}}
											render={({ openFileDialog }) => (
												<Button isSecondary isSmall onClick={openFileDialog}>
													{__('Upload Map', 'personaliseit')}
												</Button>
											)}
										/>
									</div>
								)}
							</div>
						</div>
					)}
				</div>

			</div>
		</div>
	);
};

export default SidebarLeft;

