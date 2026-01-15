/**
 * Settings - Admin settings page component
 * 
 * Orchestrates settings tabs and handles settings persistence.
 * Composed of focused tab components for maintainability.
 * 
 * @module Settings
 */
import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner, Notice, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { GeneralTab, MethodsTab, DownloadsTab, ToolsTab, AiTab } from './settings';

/**
 * Tab definitions
 */
const TABS = [
	{ name: 'general', title: __('General', 'personaliseit'), className: 'personaliseit-settings-tab-general' },
	{ name: 'methods', title: __('Personalisation Methods', 'personaliseit'), className: 'personaliseit-settings-tab-methods' },
	{ name: 'downloads', title: __('File Downloads', 'personaliseit'), className: 'personaliseit-settings-tab-downloads' },
	{ name: 'tools', title: __('Tools & Backup', 'personaliseit'), className: 'personaliseit-settings-tab-tools' },
	{ name: 'ai', title: __('AI Integration', 'personaliseit'), className: 'personaliseit-settings-tab-ai' },
];

/**
 * Sensitive keys that need special handling via secure endpoint
 */
const SENSITIVE_KEYS = [
	'personaliseit_openrouter_api_key',
	'personaliseit_spotify_client_secret',
];

/**
 * Boolean keys that need string conversion for WP REST API
 */
const BOOLEAN_KEYS = [
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

/**
 * Settings page component
 */
const Settings = () => {
	const [settings, setSettings] = useState(null);
	const [isSaving, setIsSaving] = useState(false);
	const [notice, setNotice] = useState(null);

	// AI Models state
	const [aiModels, setAiModels] = useState([]);
	const [isLoadingModels, setIsLoadingModels] = useState(false);
	const [filterValue, setFilterValue] = useState('');

	/**
	 * Fetch AI models from OpenRouter
	 */
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
				if (err.message) msg += ' ' + err.message;
				if (err.code) msg += ` (${err.code})`;
				setNotice({ status: 'error', text: msg });
			})
			.finally(() => setIsLoadingModels(false));
	};

	/**
	 * Load settings on mount
	 */
	useEffect(() => {
		const loadSettings = async () => {
			try {
				const data = await apiFetch({ path: '/wp/v2/settings' });
				const secureData = await apiFetch({ path: '/personaliseit/v1/secure-settings' });

				const isEnabled = (val) => val === true || val === '1' || val === 1 || val === 'true';

				setSettings({
					personaliseit_canvas_width: data.personaliseit_canvas_width,
					personaliseit_canvas_height: data.personaliseit_canvas_height,
					personaliseit_max_upload_size: data.personaliseit_max_upload_size,
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
					personaliseit_openrouter_api_key: secureData.personaliseit_openrouter_api_key || '',
					personaliseit_ai_model: data.personaliseit_ai_model || 'stabilityai/stable-diffusion-xl-base-1.0',
					personaliseit_ai_style_prompt: data.personaliseit_ai_style_prompt || 'Make it look like a cartoon',
					personaliseit_enable_ai_generate: isEnabled(data.personaliseit_enable_ai_generate),
					personaliseit_enable_ai_style: isEnabled(data.personaliseit_enable_ai_style),
					personaliseit_ai_styles: data.personaliseit_ai_styles || [],
					personaliseit_enable_spotify: isEnabled(data.personaliseit_enable_spotify),
					personaliseit_spotify_client_id: data.personaliseit_spotify_client_id || '',
					personaliseit_spotify_client_secret: secureData.personaliseit_spotify_client_secret || '',
					personaliseit_enable_face_cutout: isEnabled(data.personaliseit_enable_face_cutout),
				});
			} catch (err) {
				console.error('Failed to load settings:', err);
			}
		};
		loadSettings();
	}, []);

	/**
	 * Handle setting change
	 */
	const handleChange = (key, value) => {
		setSettings({ ...settings, [key]: value });
	};

	/**
	 * Save settings
	 */
	const saveSettings = async () => {
		setIsSaving(true);
		setNotice(null);

		const payload = { ...settings };

		// Extract sensitive settings for secure endpoint
		const securePayload = {};
		SENSITIVE_KEYS.forEach(key => {
			if (typeof payload[key] !== 'undefined') {
				securePayload[key] = payload[key];
				delete payload[key];
			}
		});

		// Convert booleans to strings for WP REST API
		BOOLEAN_KEYS.forEach(key => {
			if (typeof payload[key] !== 'undefined') {
				payload[key] = payload[key] ? '1' : '0';
			}
		});

		try {
			await apiFetch({ path: '/wp/v2/settings', method: 'POST', data: payload });
			await apiFetch({ path: '/personaliseit/v1/secure-settings', method: 'POST', data: securePayload });
			setNotice({ status: 'success', text: __('Settings saved.', 'personaliseit') });
		} catch (err) {
			setNotice({ status: 'error', text: err.message });
		} finally {
			setIsSaving(false);
		}
	};

	/**
	 * Refresh settings after import
	 */
	const refreshSettings = () => {
		apiFetch({ path: '/wp/v2/settings' }).then(d =>
			setSettings(prev => ({
				...prev,
				personaliseit_canvas_width: d.personaliseit_canvas_width,
				personaliseit_canvas_height: d.personaliseit_canvas_height,
			}))
		);
	};

	/**
	 * Render active tab content
	 */
	const renderTab = (tab) => {
		switch (tab.name) {
			case 'general':
				return <GeneralTab settings={settings} onChange={handleChange} />;
			case 'methods':
				return <MethodsTab settings={settings} onChange={handleChange} />;
			case 'downloads':
				return <DownloadsTab settings={settings} onChange={handleChange} />;
			case 'tools':
				return (
					<ToolsTab
						settings={settings}
						onChange={handleChange}
						onNotice={setNotice}
						setIsSaving={setIsSaving}
						refreshSettings={refreshSettings}
					/>
				);
			case 'ai':
				return (
					<AiTab
						settings={settings}
						onChange={handleChange}
						models={aiModels}
						isLoadingModels={isLoadingModels}
						onFetchModels={fetchModels}
						filterValue={filterValue}
						onFilterChange={setFilterValue}
					/>
				);
			default:
				return null;
		}
	};

	if (!settings) {
		return (
			<div className="personaliseit-loading">
				<Spinner />
			</div>
		);
	}

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
					<Notice status={notice.status} onRemove={() => setNotice(null)}>
						{notice.text}
					</Notice>
				</div>
			)}

			<div className="personaliseit-content-card">
				<TabPanel
					className="personaliseit-settings-tabs"
					activeClass="is-active"
					tabs={TABS}
				>
					{renderTab}
				</TabPanel>
			</div>
		</div>
	);
};

export default Settings;
