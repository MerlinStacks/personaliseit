import { useState, useEffect } from '@wordpress/element';
import {
	ToggleControl,
	Button,
	TextControl,
	Spinner,
	Notice,
	Panel,
	PanelBody,
	TabPanel,
	ComboboxControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const tabs = [
	{
		name: 'general',
		title: __('General', 'personaliseit'),
		className: 'personaliseit-settings-tab-general',
	},
	{
		name: 'methods',
		title: __('Personalisation Methods', 'personaliseit'),
		className: 'personaliseit-settings-tab-methods',
	},
	{
		name: 'downloads',
		title: __('File Downloads', 'personaliseit'),
		className: 'personaliseit-settings-tab-downloads',
	},
	{
		name: 'tools',
		title: __('Tools & Backup', 'personaliseit'),
		className: 'personaliseit-settings-tab-tools',
	},
	{
		name: 'ai',
		title: __('AI Integration', 'personaliseit'),
		className: 'personaliseit-settings-tab-ai',
	},
];

const Settings = () => {
	const [settings, setSettings] = useState(null);
	const [isSaving, setIsSaving] = useState(false);
	const [notice, setNotice] = useState(null);

	// AI Models State
	const [aiModels, setAiModels] = useState([]);
	const [isLoadingModels, setIsLoadingModels] = useState(false);
	const [filterValue, setFilterValue] = useState('');

	const fetchModels = () => {
		setIsLoadingModels(true);
		apiFetch({ path: '/personaliseit/v1/ai/models' })
			.then((data) => {
				const formatted = data.map(m => ({
					value: m.id,
					label: m.name + ' (' + m.id + ')'
				}));
				setAiModels(formatted);
			})
			.catch(err => {
				console.error(err);
				let msg = __('Failed to fetch models.', 'personaliseit');
				if (err.message) {
					msg += ' ' + err.message;
				}
				if (err.code) {
					msg += ` (${err.code})`;
				}
				setNotice({ status: 'error', text: msg });
			})
			.finally(() => setIsLoadingModels(false));
	};

	// Models effect - fetch if we have key but no models yet? Or just let user click refresh?
	// Let's verify if we have models on load if key is present.
	// Actually, explicit refresh is safer to avoid auto-spamming openrouter on tab load if many users open settings.

	const filteredModels = aiModels.length > 0
		? aiModels.filter((option) =>
			option.label.toLowerCase().indexOf(filterValue.toLowerCase()) >= 0
		)
		: [];

	// If no models loaded, but we have a value, just show that value?
	// ComboboxControl often needs the value to be in the options to show label correctly?
	// If 'filteredModels' is empty, we might want to at least show the current setting as an option.
	if (settings && settings.personaliseit_ai_model && !aiModels.find(m => m.value === settings.personaliseit_ai_model)) {
		// Only if we haven't fetched yet? No, always ensure current value is an option if custom.
		// Actually, let's just push it to filtered if not present
		if (!filteredModels.find(m => m.value === settings.personaliseit_ai_model)) {
			filteredModels.unshift({
				value: settings.personaliseit_ai_model,
				label: settings.personaliseit_ai_model // Fallback label
			});
		}
	}

	// Sensitive keys that need special handling via secure endpoint
	const sensitiveKeys = [
		'personaliseit_openrouter_api_key',
		'personaliseit_spotify_client_secret',
	];

	useEffect(() => {
		const loadSettings = async () => {
			try {
				// Fetch regular settings via WP REST Settings API
				const data = await apiFetch({ path: '/wp/v2/settings' });

				// Fetch sensitive settings via our secure admin endpoint
				const secureData = await apiFetch({ path: '/personaliseit/v1/secure-settings' });

				const isEnabled = (val) => val === true || val === '1' || val === 1 || val === 'true';
				setSettings({
					personaliseit_canvas_width: data.personaliseit_canvas_width,
					personaliseit_canvas_height:
						data.personaliseit_canvas_height,
					personaliseit_max_upload_size:
						data.personaliseit_max_upload_size,
					personaliseit_enable_engraving: isEnabled(data.personaliseit_enable_engraving),
					personaliseit_enable_embroidery: isEnabled(data.personaliseit_enable_embroidery),
					personaliseit_enable_dtf: isEnabled(data.personaliseit_enable_dtf),
					personaliseit_enable_uv: isEnabled(data.personaliseit_enable_uv),
					personaliseit_enable_sublimation: isEnabled(data.personaliseit_enable_sublimation),
					personaliseit_enable_pdf_download: isEnabled(data.personaliseit_enable_pdf_download),
					personaliseit_enable_svg_download: isEnabled(data.personaliseit_enable_svg_download),
					personaliseit_enable_jpg_download: isEnabled(data.personaliseit_enable_jpg_download),
					personaliseit_enable_png_download: isEnabled(data.personaliseit_enable_png_download),
					personaliseit_show_cost: isEnabled(data.personaliseit_show_cost),
					personaliseit_label_position: data.personaliseit_label_position || 'above',
					// Sensitive settings from secure endpoint
					personaliseit_openrouter_api_key: secureData.personaliseit_openrouter_api_key || '',
					personaliseit_ai_model: data.personaliseit_ai_model || 'stabilityai/stable-diffusion-xl-base-1.0',
					personaliseit_ai_style_prompt: data.personaliseit_ai_style_prompt || 'Make it look like a cartoon',
					personaliseit_enable_ai_generate: isEnabled(data.personaliseit_enable_ai_generate),
					personaliseit_enable_ai_style: isEnabled(data.personaliseit_enable_ai_style),
					personaliseit_ai_styles: data.personaliseit_ai_styles || [],
					personaliseit_enable_spotify: isEnabled(data.personaliseit_enable_spotify),
					personaliseit_spotify_client_id: data.personaliseit_spotify_client_id || '',
					// Sensitive settings from secure endpoint
					personaliseit_spotify_client_secret: secureData.personaliseit_spotify_client_secret || '',
					personaliseit_enable_face_cutout: isEnabled(data.personaliseit_enable_face_cutout),
				});
			} catch (err) {
				console.error('Failed to load settings:', err);
			}
		};
		loadSettings();
	}, []);

	const handleChange = (key, value) => {
		setSettings({ ...settings, [key]: value });
	};

	const saveSettings = async () => {
		setIsSaving(true);
		setNotice(null);

		const payload = { ...settings };

		// Extract sensitive settings for secure endpoint
		const securePayload = {};
		sensitiveKeys.forEach(key => {
			if (typeof payload[key] !== 'undefined') {
				securePayload[key] = payload[key];
				delete payload[key]; // Remove from regular payload
			}
		});

		// Convert booleans to strings to avoid type validation issues in WP REST API
		const stringBoolKeys = [
			'personaliseit_enable_engraving',
			'personaliseit_enable_embroidery',
			'personaliseit_enable_dtf',
			'personaliseit_enable_uv',
			'personaliseit_enable_sublimation',
			'personaliseit_enable_pdf_download',
			'personaliseit_enable_svg_download',
			'personaliseit_enable_jpg_download',
			'personaliseit_enable_png_download',
			'personaliseit_enable_spotify',
			'personaliseit_enable_face_cutout',
		];
		stringBoolKeys.forEach(key => {
			if (typeof payload[key] !== 'undefined') {
				payload[key] = payload[key] ? '1' : '0';
			}
		});

		try {
			// Save regular settings via WP REST Settings API
			await apiFetch({
				path: '/wp/v2/settings',
				method: 'POST',
				data: payload,
			});

			// Save sensitive settings via secure admin endpoint
			await apiFetch({
				path: '/personaliseit/v1/secure-settings',
				method: 'POST',
				data: securePayload,
			});

			setNotice({
				status: 'success',
				text: __('Settings saved.', 'personaliseit'),
			});
		} catch (err) {
			setNotice({ status: 'error', text: err.message });
		} finally {
			setIsSaving(false);
		}
	};

	if (!settings)
		return (
			<div className="personaliseit-loading">
				<Spinner />
			</div>
		);

	return (
		<div className="wrap personaliseit-settings-page">
			<div className="personaliseit-header">
				<div className="header-title">
					<h1 className="wp-heading-inline">{__('Personalise It!', 'personaliseit')}</h1>
					<span className="version-badge">v3.5.1</span>
				</div>
				<Button
					isPrimary
					isBusy={isSaving}
					onClick={saveSettings}
					className="save-button"
				>
					{__('Save Changes', 'personaliseit')}
				</Button>
			</div>

			{notice && (
				<div className="personaliseit-notice-wrapper">
					<Notice
						status={notice.status}
						onRemove={() => setNotice(null)}
					>
						{notice.text}
					</Notice>
				</div>
			)}

			<div className="personaliseit-content-card">
				<TabPanel
					className="personaliseit-settings-tabs"
					activeClass="is-active"
					tabs={tabs}
				>
					{(tab) => {
						if (tab.name === 'general') {
							return (
								<div className="tab-content animate-fade-in">
									<div className="section-block">
										<h2 className="section-title">{__('Canvas Defaults', 'personaliseit')}</h2>
										<div className="form-row two-col">
											<TextControl
												label={__('Default Width (px)', 'personaliseit')}
												type="number"
												value={settings.personaliseit_canvas_width}
												onChange={(val) => handleChange('personaliseit_canvas_width', parseInt(val))}
											/>
											<TextControl
												label={__('Default Height (px)', 'personaliseit')}
												type="number"
												value={settings.personaliseit_canvas_height}
												onChange={(val) => handleChange('personaliseit_canvas_height', parseInt(val))}
											/>
										</div>
									</div>

									<div className="section-block">
										<h2 className="section-title">{__('Upload Restrictions', 'personaliseit')}</h2>
										<TextControl
											label={__('Max Image Upload Size (MB)', 'personaliseit')}
											help={__('Limit the size of images customers can upload.', 'personaliseit')}
											type="number"
											value={settings.personaliseit_max_upload_size}
											onChange={(val) => handleChange('personaliseit_max_upload_size', parseInt(val))}
										/>
									</div>

									<div className="section-block">
										<h2 className="section-title">{__('Display Options', 'personaliseit')}</h2>
										<div className="toggle-group">
											<ToggleControl
												label={__('Show Personalisation Cost', 'personaliseit')}
												help={__('Display the additional cost for personalisation on the frontend.', 'personaliseit')}
												checked={settings.personaliseit_show_cost}
												onChange={(val) => handleChange('personaliseit_show_cost', val)}
											/>
										</div>

										<div className="control-group">
											<label className="control-label">{__('Input Label Position', 'personaliseit')}</label>
											<select
												value={settings.personaliseit_label_position}
												onChange={(e) => handleChange('personaliseit_label_position', e.target.value)}
												className="regular-select"
											>
												<option value="above">{__('Above Control', 'personaliseit')}</option>
												<option value="below">{__('Below Control', 'personaliseit')}</option>
												<option value="left">{__('Left of Control', 'personaliseit')}</option>
												<option value="right">{__('Right of Control', 'personaliseit')}</option>
											</select>
											<p className="description">{__('Choose how labels are positioned relative to the input fields on the frontend.', 'personaliseit')}</p>
										</div>
									</div>
								</div>
							);
						} else if (tab.name === 'methods') {
							return (
								<div className="tab-content animate-fade-in">
									<p className="tab-description">
										{__('Enable or disable personalisation methods available for products.', 'personaliseit')}
									</p>
									<div className="grid-options">
										{[
											{ key: 'engraving', label: 'Engraving', description: __('Ideal for metal/wood. Restricts designs to monochrome or single texture. Often disables full color selection.', 'personaliseit') },
											{ key: 'embroidery', label: 'Embroidery', description: __('Simulates stitched thread. May limit colors to specific thread palettes and adds texture effects.', 'personaliseit') },
											{ key: 'dtf', label: 'DTF Printing', description: __('Direct Transfer Film. Enables full-color, high-detail prints suitable for most apparel.', 'personaliseit') },
											{ key: 'uv', label: 'UV Printing', description: __('Direct UV curing. Enables full-color printing on hard surfaces (mugs, pens, cases).', 'personaliseit') },
											{ key: 'sublimation', label: 'Sublimation', description: __('Dye transfer. best for white polyester or coated items. Allows full color and gradient designs.', 'personaliseit') },
										].map(method => (
											<div key={method.key} className="option-card" style={{ flexDirection: 'column', alignItems: 'flex-start', gap: '10px' }}>
												<ToggleControl
													label={<span style={{ fontWeight: '600' }}>{method.label}</span>}
													checked={settings['personaliseit_enable_' + method.key]}
													onChange={(val) => handleChange('personaliseit_enable_' + method.key, val)}
												/>
												<p style={{ margin: 0, fontSize: '13px', color: '#666', lineHeight: '1.4' }}>
													{method.description}
												</p>
											</div>
										))}
									</div>
								</div>
							);
						} else if (tab.name === 'downloads') {
							return (
								<div className="tab-content animate-fade-in">
									<p className="tab-description">
										{__('Select which file formats are available for download on the order page.', 'personaliseit')}
									</p>
									<div className="grid-options">
										{[
											{ key: 'pdf_download', label: 'PDF' },
											{ key: 'svg_download', label: 'SVG' },
											{ key: 'jpg_download', label: 'JPG' },
											{ key: 'png_download', label: 'PNG' },
										].map(format => (
											<div key={format.key} className="option-card">
												<ToggleControl
													label={format.label}
													checked={settings['personaliseit_enable_' + format.key]}
													onChange={(val) => handleChange('personaliseit_enable_' + format.key, val)}
												/>
											</div>
										))}
									</div>
								</div>
							);
						} else if (tab.name === 'tools') {
							return (
								<div className="tab-content animate-fade-in">
									<p className="tab-description">{__('Export plugin data (settings, fonts, assets, product configs) to a JSON file, or restore from a backup.', 'personaliseit')}</p>

									<div className="section-block">
										<h2 className="section-title">{__('Spotify Integration', 'personaliseit')}</h2>
										<ToggleControl
											label={__('Enable Spotify Search', 'personaliseit')}
											checked={settings.personaliseit_enable_spotify}
											onChange={(val) => handleChange('personaliseit_enable_spotify', val)}
										/>
										{settings.personaliseit_enable_spotify && (
											<div style={{ marginLeft: '20px', marginTop: '10px' }}>
												<TextControl
													label={__('Client ID', 'personaliseit')}
													value={settings.personaliseit_spotify_client_id}
													onChange={(val) => handleChange('personaliseit_spotify_client_id', val)}
												/>
												<TextControl
													label={__('Client Secret', 'personaliseit')}
													type="password"
													value={settings.personaliseit_spotify_client_secret}
													onChange={(val) => handleChange('personaliseit_spotify_client_secret', val)}
												/>
											</div>
										)}
									</div>

									<div className="section-block">
										<h2 className="section-title">{__('Face Cutout Integration', 'personaliseit')}</h2>
										<ToggleControl
											label={__('Enable Face Cutout (Local AI)', 'personaliseit')}
											checked={settings.personaliseit_enable_face_cutout}
											onChange={(val) => handleChange('personaliseit_enable_face_cutout', val)}
											help={__('Uses your device to remove backgrounds. No API key required.', 'personaliseit')}
										/>
									</div>

									<div className="tools-card">
										<div className="tool-column">
											<h3 className="tool-title">{__('Backup', 'personaliseit')}</h3>
											<p className="tool-desc">{__('Download a complete backup of your personalisation settings.', 'personaliseit')}</p>
											<Button
												isSecondary
												onClick={async () => {
													try {
														const data = await apiFetch({ path: '/personaliseit/v1/export' });
														const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(data, null, 2));
														const downloadAnchorNode = document.createElement('a');
														downloadAnchorNode.setAttribute("href", dataStr);
														downloadAnchorNode.setAttribute("download", "personaliseit_backup_" + new Date().toISOString().slice(0, 10) + ".json");
														document.body.appendChild(downloadAnchorNode);
														downloadAnchorNode.click();
														downloadAnchorNode.remove();
													} catch (err) {
														setNotice({ status: 'error', text: 'Export failed: ' + err.message });
													}
												}}
											>
												{__('Download Backup', 'personaliseit')}
											</Button>
										</div>

										<div className="tool-divider"></div>

										<div className="tool-column">
											<h3 className="tool-title">{__('Restore', 'personaliseit')}</h3>
											<p className="tool-desc">{__('Upload a previously saved backup file.', 'personaliseit')}</p>
											<label
												className="components-button is-secondary"
												style={{ cursor: 'pointer' }}
											>
												{__('Import Backup', 'personaliseit')}
												<input
													type="file"
													accept=".json"
													style={{ display: 'none' }}
													onChange={(event) => {
														const file = event.target.files[0];
														if (!file) return;
														if (!confirm(__('This will overwrite existing settings and add data. Continue?', 'personaliseit'))) return;

														const reader = new FileReader();
														reader.onload = async (e) => {
															try {
																setIsSaving(true);
																const json = JSON.parse(e.target.result);
																await apiFetch({
																	path: '/personaliseit/v1/import',
																	method: 'POST',
																	data: { data: json }
																});
																setNotice({ status: 'success', text: __('Import successful!', 'personaliseit') });
															} catch (err) {
																setNotice({ status: 'error', text: __('Import failed: ', 'personaliseit') + err.message });
															} finally {
																setIsSaving(false);
																// Refresh settings display
																apiFetch({ path: '/wp/v2/settings' }).then(d =>
																	setSettings(prev => ({
																		...prev,
																		personaliseit_canvas_width: d.personaliseit_canvas_width,
																		personaliseit_canvas_height: d.personaliseit_canvas_height,

																	}))
																);
															}
														};
														reader.readAsText(file);
													}}
												/>
											</label>
										</div>
									</div>
								</div>
							);
						} else if (tab.name === 'ai') {
							return (
								<div className="tab-content animate-fade-in">
									<div className="section-block">
										<h2 className="section-title">{__('OpenRouter Integration', 'personaliseit')}</h2>
										<p className="description" style={{ marginBottom: '15px' }}>
											{__('Enter your OpenRouter API Key to enable AI features. These features act on behalf of your account.', 'personaliseit')}
										</p>
										<TextControl
											label={__('OpenRouter API Key', 'personaliseit')}
											type="password"
											value={settings.personaliseit_openrouter_api_key}
											onChange={(val) => handleChange('personaliseit_openrouter_api_key', val)}
											help={__('Get your key from openrouter.ai', 'personaliseit')}
										/>

										<div className="control-group">
											<label className="control-label">{__('AI Model', 'personaliseit')}</label>
											<div style={{ display: 'flex', gap: '10px', alignItems: 'flex-start' }}>
												<div style={{ flex: 1 }}>
													<ComboboxControl
														label=""
														value={settings.personaliseit_ai_model}
														onChange={(val) => handleChange('personaliseit_ai_model', val)}
														options={filteredModels}
														onFilterValueChange={setFilterValue}
														allowReset={false}
													/>
												</div>
												<Button
													isSecondary
													onClick={fetchModels}
													isBusy={isLoadingModels}
													disabled={!settings.personaliseit_openrouter_api_key}
												>
													{__('Refresh Models', 'personaliseit')}
												</Button>
											</div>
											<p className="description">
												{__('Search or enter a model ID (e.g., stabilityai/stable-diffusion-xl-base-1.0).', 'personaliseit')}
											</p>
										</div>
									</div>

									<div className="section-block">
										<h2 className="section-title">{__('Feature Controls', 'personaliseit')}</h2>
										<ToggleControl
											label={__('Enable Image Generation', 'personaliseit')}
											checked={settings.personaliseit_enable_ai_generate}
											onChange={(val) => handleChange('personaliseit_enable_ai_generate', val)}
											help={__('Allow users to generate images from scratch using prompts.', 'personaliseit')}
										/>
										<div className="section-divider"></div>
										<ToggleControl
											label={__('Enable Style Transfer', 'personaliseit')}
											checked={settings.personaliseit_enable_ai_style}
											onChange={(val) => handleChange('personaliseit_enable_ai_style', val)}
											help={__('Allow users to restyle uploaded images.', 'personaliseit')}
										/>
										{settings.personaliseit_enable_ai_style && (
											<div style={{ marginTop: '10px', marginLeft: '20px', borderLeft: '2px solid #eee', paddingLeft: '10px' }}>
												<h3 style={{ fontSize: '14px', margin: '10px 0' }}>{__('Unified Style Prompt', 'personaliseit')}</h3>
												<TextControl
													label={__('Default Prompt Suffix', 'personaliseit')}
													value={settings.personaliseit_ai_style_prompt}
													onChange={(val) => handleChange('personaliseit_ai_style_prompt', val)}
													help={__('Used if no specific preset is selected. E.g. "Make it look like a cartoon"', 'personaliseit')}
												/>
												<p className="description" style={{ marginTop: '10px', fontStyle: 'italic' }}>
													{__('Tip: Manage advanced Style Presets in the "Artist Styles" menu.', 'personaliseit')}
												</p>
											</div>
										)}
									</div>
								</div>
							);
						}
					}}
				</TabPanel>
			</div>
		</div>
	);
};
export default Settings;
