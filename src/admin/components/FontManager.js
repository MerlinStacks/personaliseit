import { useState, useEffect, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Spinner,
	FormFileUpload,
	Card,
	CardBody,
	Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import FontService from '../../common/services/FontService';
import { showToast } from '../../common/components/Toast';

const FontManager = () => {
	const [fonts, setFonts] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	const [isUploading, setIsUploading] = useState(false);
	const [editingFont, setEditingFont] = useState(null); // The font object being edited

	// We don't need fileInputRef for the modal logic anymore

	const [isAddingGoogleFont, setIsAddingGoogleFont] = useState(false);

	const handleAddGoogleFont = async (family, url) => {
		setIsLoading(true);
		setIsAddingGoogleFont(false);
		try {
			const result = await FontService.addGoogleFont(family, url);
			setFonts([...fonts, result]);
			showToast.success(__('Google Font added successfully!', 'personaliseit'));
		} catch (error) {
			console.error(error);
			showToast.error(__('Failed to add Google Font. Check URL.', 'personaliseit'));
		} finally {
			setIsLoading(false);
		}
	};

	useEffect(() => {
		fetchFonts();
	}, []);

	const handleEdit = (font) => {
		setEditingFont(font);
	};

	const handleCloseModal = () => {
		setEditingFont(null);
	};

	const handleUploadVariation = async (event, type) => {
		const file = event.target.files[0];
		if (!file || !editingFont) return;

		setIsUploading(true);
		const formData = new FormData();
		formData.append('file', file);
		// We could pass type if API supported explicit type, but API infers from extension. 
		// We trust API Inference or we could append param.

		try {
			const result = await FontService.uploadFont(file, { fontId: editingFont.id });

			// Update state
			const updatedFont = result;
			setFonts(fonts.map(f => f.id === editingFont.id ? updatedFont : f));
			setEditingFont(updatedFont); // Update modal view
			showToast.success(__('Font variation updated successfully', 'personaliseit'));
		} catch (error) {
			console.error(error);
			showToast.error(__('Update failed', 'personaliseit'));
		} finally {
			setIsUploading(false);
		}
	};

	// ... (handleDelete) ...


	const fetchFonts = async () => {
		setIsLoading(true);
		try {
			const results = await FontService.getFonts();
			setFonts(results);
			// Also ensure they are loaded in DOM for previews if not using Card manual styles
			FontService.loadFontsIntoDom(results);
		} catch (error) {
			console.error(error);
		} finally {
			setIsLoading(false);
		}
	};

	const handleUpload = async (event) => {
		const file = event.target.files[0];
		if (!file) return;

		setIsUploading(true);
		const formData = new FormData();
		formData.append('file', file);
		formData.append('title', file.name.replace(/\.[^/.]+$/, '')); // Remove extension

		try {
			const result = await FontService.uploadFont(file, { title: file.name.replace(/\.[^/.]+$/, '') });
			setFonts([...fonts, result]);
		} catch (error) {
			console.error(error);
			showToast.error(__('Upload failed', 'personaliseit'));
		} finally {
			setIsUploading(false);
		}
	};

	const handleDelete = async (id) => {
		if (!confirm(__('Are you sure?', 'personaliseit'))) return;

		try {
			await FontService.deleteFont(id);
			setFonts(fonts.filter((font) => font.id !== id));
		} catch (error) {
			console.error(error);
		}
	};

	if (isLoading) {
		return (
			<div className="font-manager-loading">
				<Spinner />
				<p>{__('Loading fonts...', 'personaliseit')}</p>
			</div>
		);
	}

	return (
		<div className="font-manager">
			<div className="font-manager-header">
				<div>
					<h2>{__('Font Library', 'personaliseit')}</h2>
					<p className="description">
						{__(
							'Upload custom fonts to use in your product designs',
							'personaliseit'
						)}
					</p>
				</div>
				<div style={{ display: 'flex', gap: '10px' }}>
					<FormFileUpload
						accept=".ttf,.otf,.woff,.woff2"
						onChange={handleUpload}
						isBusy={isUploading}
						render={({ openFileDialog }) => (
							<Button
								variant="primary"
								onClick={openFileDialog}
								isBusy={isUploading}
							>
								{isUploading
									? __('Uploading...', 'personaliseit')
									: __('Upload Font', 'personaliseit')}
							</Button>
						)}
					/>
					<Button
						variant="secondary"
						onClick={() => setIsAddingGoogleFont(true)}
					>
						{__('Add Google Font', 'personaliseit')}
					</Button>
				</div>
			</div>

			{isAddingGoogleFont && (
				<Modal title={__('Add Google Font', 'personaliseit')} onRequestClose={() => setIsAddingGoogleFont(false)}>
					<div className="google-font-modal-content" style={{ minWidth: 350 }}>
						<p>{__('Select a popular font to add to your library.', 'personaliseit')}</p>
						<select
							style={{ width: '100%', marginBottom: 15 }}
							onChange={(e) => {
								const val = e.target.value;
								if (!val) return;
								const [family, url] = val.split('|');
								// Confirm add
								if (confirm(__('Add ' + family + ' to library?', 'personaliseit'))) {
									handleAddGoogleFont(family, url);
								}
							}}
						>
							<option value="">{__('Select a Font...', 'personaliseit')}</option>
							<option value="Open Sans|https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;700&display=swap">Open Sans</option>
							<option value="Lato|https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap">Lato</option>
							<option value="Montserrat|https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap">Montserrat</option>
							<option value="Oswald|https://fonts.googleapis.com/css2?family=Oswald:wght@400;700&display=swap">Oswald</option>
							<option value="Source Sans Pro|https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;700&display=swap">Source Sans Pro</option>
							<option value="Raleway|https://fonts.googleapis.com/css2?family=Raleway:wght@400;700&display=swap">Raleway</option>
							<option value="Merriweather|https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&display=swap">Merriweather</option>
							<option value="Playfair Display|https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap">Playfair Display</option>
							<option value="Anton|https://fonts.googleapis.com/css2?family=Anton&display=swap">Anton</option>
							<option value="Bebas Neue|https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap">Bebas Neue</option>
							<option value="Lobster|https://fonts.googleapis.com/css2?family=Lobster&display=swap">Lobster</option>
							<option value="Pacifico|https://fonts.googleapis.com/css2?family=Pacifico&display=swap">Pacifico</option>
						</select>

						<hr />
						<p><strong>{__('Or Custom URL', 'personaliseit')}</strong></p>
						<form onSubmit={(e) => {
							e.preventDefault();
							const name = e.target.elements.gName.value;
							const url = e.target.elements.gUrl.value;
							if (name && url) handleAddGoogleFont(name, url);
						}}>
							<input name="gName" type="text" placeholder="Font Family Name" style={{ width: '100%', marginBottom: 10 }} required />
							<input name="gUrl" type="url" placeholder="Google Fonts CSS URL" style={{ width: '100%', marginBottom: 10 }} required />
							<Button isPrimary type="submit">{__('Add Custom', 'personaliseit')}</Button>
						</form>
					</div>
				</Modal>
			)}

			{editingFont && (
				<Modal title={`Edit Font: ${editingFont.family}`} onRequestClose={handleCloseModal}>
					<div className="font-edit-modal-content">
						<p>{__('Upload specific file formats to enable better browser support and vector export.', 'personaliseit')}</p>

						<table className="widefat striped" style={{ marginTop: '15px' }}>
							<thead>
								<tr>
									<th>{__('Format', 'personaliseit')}</th>
									<th>{__('Status', 'personaliseit')}</th>
									<th>{__('Action', 'personaliseit')}</th>
								</tr>
							</thead>
							<tbody>
								{/* TTF */}
								<tr>
									<td><strong>TTF</strong> <span className="description">(TrueType)</span><br /><small>Best for Vector Export & Curves</small></td>
									<td>
										{(editingFont.source === 'google') ?
											<span style={{ color: '#007cba' }}>{__('Served via Google CDN', 'personaliseit')}</span> :
											(editingFont.files && editingFont.files.ttf ?
												<span style={{ color: 'green', fontWeight: 'bold' }}>&#10003; {__('Uploaded', 'personaliseit')}</span> :
												<span style={{ color: '#666' }}>{__('Missing', 'personaliseit')}</span>
											)
										}
									</td>
									<td>
										<FormFileUpload
											accept=".ttf"
											onChange={(e) => handleUploadVariation(e, 'ttf')}
											isBusy={isUploading}
											render={({ openFileDialog }) => (
												<Button isSecondary onClick={openFileDialog} disabled={!!(editingFont.files && editingFont.files.ttf)}>
													{(editingFont.files && editingFont.files.ttf) ? __('Exists', 'personaliseit') : __('Upload TTF', 'personaliseit')}
												</Button>
											)}
										/>
									</td>
								</tr>

								{/* WOFF2 */}
								<tr>
									<td><strong>WOFF2</strong> <span className="description">(Web Open Font Format 2)</span><br /><small>Best for Modern Browsers</small></td>
									<td>
										{(editingFont.source === 'google') ?
											<span style={{ color: '#007cba' }}>{__('Served via Google CDN', 'personaliseit')}</span> :
											(editingFont.files && editingFont.files.woff2 ?
												<span style={{ color: 'green', fontWeight: 'bold' }}>&#10003; {__('Uploaded', 'personaliseit')}</span> :
												<span style={{ color: '#666' }}>{__('Missing', 'personaliseit')}</span>
											)
										}
									</td>
									<td>
										<FormFileUpload
											accept=".woff2"
											onChange={(e) => handleUploadVariation(e, 'woff2')}
											isBusy={isUploading}
											render={({ openFileDialog }) => (
												<Button isSecondary onClick={openFileDialog} disabled={!!(editingFont.files && editingFont.files.woff2)}>
													{(editingFont.files && editingFont.files.woff2) ? __('Exists', 'personaliseit') : __('Upload WOFF2', 'personaliseit')}
												</Button>
											)}
										/>
									</td>
								</tr>

								{/* WOFF */}
								<tr>
									<td><strong>WOFF</strong> <span className="description">(Web Open Font Format)</span><br /><small>Legacy Browser Support</small></td>
									<td>
										{(editingFont.source === 'google') ?
											<span style={{ color: '#007cba' }}>{__('Served via Google CDN', 'personaliseit')}</span> :
											(editingFont.files && editingFont.files.woff ?
												<span style={{ color: 'green', fontWeight: 'bold' }}>&#10003; {__('Uploaded', 'personaliseit')}</span> :
												<span style={{ color: '#666' }}>{__('Missing', 'personaliseit')}</span>
											)
										}
									</td>
									<td>
										<FormFileUpload
											accept=".woff"
											onChange={(e) => handleUploadVariation(e, 'woff')}
											isBusy={isUploading}
											render={({ openFileDialog }) => (
												<Button isSecondary onClick={openFileDialog} disabled={!!(editingFont.files && editingFont.files.woff)}>
													{(editingFont.files && editingFont.files.woff) ? __('Exists', 'personaliseit') : __('Upload WOFF', 'personaliseit')}
												</Button>
											)}
										/>
									</td>
								</tr>
							</tbody>
						</table>

						<div style={{ marginTop: '20px', textAlign: 'right' }}>
							<Button isPrimary onClick={handleCloseModal}>
								{__('Done', 'personaliseit')}
							</Button>
						</div>
					</div>
				</Modal>
			)}

			{fonts.length === 0 ? (
				<div className="font-manager-empty">
					<div className="empty-state">
						<span
							className="dashicons dashicons-editor-textcolor"
							style={{ fontSize: '48px', opacity: 0.3 }}
						></span>
						<h3>{__('No fonts yet', 'personaliseit')}</h3>
						<p>
							{__(
								'Upload your first custom font to get started',
								'personaliseit'
							)}
						</p>
					</div>
				</div>
			) : (
				<div className="font-grid">
					{fonts.map((font) => (
						<Card key={font.id} className="font-card">
							<CardBody>
								{(font.url && (font.url.includes('googleapis.com') || font.source === 'google')) ? (
									<link rel="stylesheet" href={font.url} />
								) : (
									font.url && (
										<style>
											{`@font-face { font-family: '${font.family}'; src: url('${font.url}'); }`}
										</style>
									)
								)}

								<div className="font-card-header">
									<h3 className="font-family-name">
										{font.family}
									</h3>
									{typeof font.id === 'number' && (
										<div style={{ display: 'flex', gap: '5px' }}>
											<Button
												icon="edit"
												isSecondary
												isSmall
												onClick={() => handleEdit(font)}
												label={__('Edit / Versions', 'personaliseit')}
											/>
											<Button
												icon="trash"
												isDestructive
												isSmall
												onClick={() => handleDelete(font.id)}
												label={__('Delete', 'personaliseit')}
											/>
										</div>
									)}
								</div>

								<div
									className="font-preview"
									style={{ fontFamily: font.family }}
								>
									<div className="preview-large">Aa</div>
									<div className="preview-text">
										The quick brown fox jumps over the lazy
										dog
									</div>
									<div className="preview-numbers">
										0123456789
									</div>
								</div>

								{typeof font.id !== 'number' && (
									<div className="font-badge">
										<span className="badge-system">
											{__(
												'System Font',
												'personaliseit'
											)}
										</span>
									</div>
								)}
							</CardBody>
						</Card>
					))}
				</div>
			)}
		</div>
	);
};

export default FontManager;
