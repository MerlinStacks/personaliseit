/**
 * ToolsTab - Tools and backup settings tab
 * 
 * Spotify integration, Face Cutout, and backup/restore functionality.
 * 
 * @module ToolsTab
 */
import { useState } from '@wordpress/element';
import { ToggleControl, TextControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Tools settings tab
 * @param {Object} props - Component props
 * @param {Object} props.settings - Settings object
 * @param {Function} props.onChange - Settings change handler
 * @param {Function} props.onNotice - Notice callback
 * @param {Function} props.setIsSaving - Set saving state
 * @param {Function} props.refreshSettings - Refresh settings callback
 */
const ToolsTab = ({ settings, onChange, onNotice, setIsSaving, refreshSettings }) => {
    /**
     * Handle backup export
     */
    const handleExport = async () => {
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
            onNotice({ status: 'error', text: 'Export failed: ' + err.message });
        }
    };

    /**
     * Handle backup import
     */
    const handleImport = (event) => {
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
                onNotice({ status: 'success', text: __('Import successful!', 'personaliseit') });
                refreshSettings();
            } catch (err) {
                onNotice({ status: 'error', text: __('Import failed: ', 'personaliseit') + err.message });
            } finally {
                setIsSaving(false);
            }
        };
        reader.readAsText(file);
    };

    return (
        <div className="tab-content animate-fade-in">
            <p className="tab-description">
                {__('Export plugin data (settings, fonts, assets, product configs) to a JSON file, or restore from a backup.', 'personaliseit')}
            </p>

            <div className="section-block">
                <h2 className="section-title">{__('Spotify Integration', 'personaliseit')}</h2>
                <ToggleControl
                    label={__('Enable Spotify Search', 'personaliseit')}
                    checked={settings.personaliseit_enable_spotify}
                    onChange={(val) => onChange('personaliseit_enable_spotify', val)}
                />
                {settings.personaliseit_enable_spotify && (
                    <div className="nested-settings">
                        <TextControl
                            label={__('Client ID', 'personaliseit')}
                            value={settings.personaliseit_spotify_client_id}
                            onChange={(val) => onChange('personaliseit_spotify_client_id', val)}
                        />
                        <TextControl
                            label={__('Client Secret', 'personaliseit')}
                            type="password"
                            value={settings.personaliseit_spotify_client_secret}
                            onChange={(val) => onChange('personaliseit_spotify_client_secret', val)}
                        />
                    </div>
                )}
            </div>

            <div className="section-block">
                <h2 className="section-title">{__('Face Cutout Integration', 'personaliseit')}</h2>
                <ToggleControl
                    label={__('Enable Face Cutout (Local AI)', 'personaliseit')}
                    checked={settings.personaliseit_enable_face_cutout}
                    onChange={(val) => onChange('personaliseit_enable_face_cutout', val)}
                    help={__('Uses your device to remove backgrounds. No API key required.', 'personaliseit')}
                />
            </div>

            <div className="tools-card">
                <div className="tool-column">
                    <h3 className="tool-title">{__('Backup', 'personaliseit')}</h3>
                    <p className="tool-desc">
                        {__('Download a complete backup of your personalisation settings.', 'personaliseit')}
                    </p>
                    <Button isSecondary onClick={handleExport}>
                        {__('Download Backup', 'personaliseit')}
                    </Button>
                </div>

                <div className="tool-divider"></div>

                <div className="tool-column">
                    <h3 className="tool-title">{__('Restore', 'personaliseit')}</h3>
                    <p className="tool-desc">
                        {__('Upload a previously saved backup file.', 'personaliseit')}
                    </p>
                    <label className="components-button is-secondary upload-label">
                        {__('Import Backup', 'personaliseit')}
                        <input
                            type="file"
                            accept=".json"
                            className="pi-hidden-input"
                            onChange={handleImport}
                        />
                    </label>
                </div>
            </div>
        </div>
    );
};

export default ToolsTab;
