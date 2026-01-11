import { useState, useEffect } from '@wordpress/element';
import {
    Button,
    TextControl,
    Card,
    CardBody,
    CardHeader,
    Spinner,
    Notice,
    TextareaControl,
    CheckboxControl
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { FormFileUpload } from '@wordpress/components';

const StyleManager = () => {
    const [styles, setStyles] = useState([]);
    const [selectedStyle, setSelectedStyle] = useState(null); // { id, title, prompt, preview_url, remove_bg }
    const [isLoading, setIsLoading] = useState(true);
    const [isSaving, setIsSaving] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [notice, setNotice] = useState(null);

    useEffect(() => {
        fetchStyles();
    }, []);

    const fetchStyles = async () => {
        try {
            // We fetch post type 'personaliseit_style'
            const data = await apiFetch({ path: '/wp/v2/personaliseit_style?per_page=100' });

            // Map to our internal format
            const formatted = data.map(item => ({
                id: item.id,
                title: item.title.rendered,
                prompt: item.meta?.personaliseit_style_prompt || '',
                preview_url: item.meta?.personaliseit_style_preview || '',
                remove_bg: item.meta?.personaliseit_style_remove_bg || false,
            }));

            setStyles(formatted);
            setIsLoading(false);
        } catch (err) {
            console.error(err);
            setNotice({ status: 'error', text: 'Error fetching styles. Ensure CPT is accessible via REST.' });
            setIsLoading(false);
        }
    };

    const handleSelectStyle = (style) => {
        setSelectedStyle({ ...style });
        setNotice(null);
    };

    const handleCreateStyle = () => {
        setSelectedStyle({ id: 'new', title: 'New Style', prompt: '', preview_url: '', remove_bg: false });
        setNotice(null);
    };

    const handleImageUpload = async (event) => {
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
            setSelectedStyle({ ...selectedStyle, preview_url: response.source_url });
            setNotice({ status: 'success', text: __('Image uploaded successfully.', 'personaliseit') });
        } catch (err) {
            console.error(err);
            setNotice({ status: 'error', text: __('Upload failed: ', 'personaliseit') + err.message });
        } finally {
            setIsUploading(false);
        }
    };

    const handleSave = async () => {
        if (!selectedStyle) return;

        setIsSaving(true);
        try {
            let result;
            const payload = {
                title: selectedStyle.title,
                status: 'publish',
                meta: {
                    personaliseit_style_prompt: selectedStyle.prompt,
                    personaliseit_style_preview: selectedStyle.preview_url,
                    personaliseit_style_remove_bg: selectedStyle.remove_bg,
                }
            };

            if (selectedStyle.id === 'new') {
                const response = await apiFetch({
                    path: '/wp/v2/personaliseit_style',
                    method: 'POST',
                    data: payload,
                });

                const newStyle = {
                    id: response.id,
                    title: response.title.rendered,
                    prompt: response.meta?.personaliseit_style_prompt || '',
                    preview_url: response.meta?.personaliseit_style_preview || '',
                    remove_bg: response.meta?.personaliseit_style_remove_bg || false,
                };

                setStyles([...styles, newStyle]);
                setSelectedStyle(newStyle);
            } else {
                const response = await apiFetch({
                    path: `/wp/v2/personaliseit_style/${selectedStyle.id}`,
                    method: 'POST',
                    data: payload,
                });

                const updatedStyle = {
                    id: response.id,
                    title: response.title.rendered,
                    prompt: response.meta?.personaliseit_style_prompt || '',
                    preview_url: response.meta?.personaliseit_style_preview || '',
                    remove_bg: response.meta?.personaliseit_style_remove_bg || false,
                };

                setStyles(
                    styles.map((s) => s.id === selectedStyle.id ? updatedStyle : s)
                );
                setSelectedStyle(updatedStyle);
            }
            setNotice({ status: 'success', text: __('Style saved.', 'personaliseit') });
        } catch (err) {
            console.error(err);
            setNotice({ status: 'error', text: err.message });
        } finally {
            setIsSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!selectedStyle || selectedStyle.id === 'new') {
            setSelectedStyle(null);
            return;
        }

        if (!confirm(__('Are you sure you want to delete this style?', 'personaliseit'))) return;

        setIsSaving(true);
        try {
            await apiFetch({
                path: `/wp/v2/personaliseit_style/${selectedStyle.id}`,
                method: 'DELETE',
            });
            setStyles(styles.filter((s) => s.id !== selectedStyle.id));
            setSelectedStyle(null);
            setNotice({ status: 'success', text: __('Style deleted.', 'personaliseit') });
        } catch (err) {
            setNotice({ status: 'error', text: err.message });
        } finally {
            setIsSaving(false);
        }
    };

    if (isLoading) {
        return (
            <div style={{ padding: '20px', textAlign: 'center' }}>
                <Spinner />
            </div>
        );
    }

    return (
        <div className="wrap personaliseit-styles" style={{ display: 'flex', height: 'calc(100vh - 100px)', gap: '20px', padding: '20px' }}>

            {/* Sidebar List */}
            <div style={{ width: '250px', borderRight: '1px solid #ddd', paddingRight: '20px', overflowY: 'auto' }}>
                <Button
                    isPrimary
                    onClick={handleCreateStyle}
                    style={{ width: '100%', marginBottom: '20px' }}
                >
                    {__('Add New Style', 'personaliseit')}
                </Button>

                <div className="style-list">
                    {styles.map((style) => (
                        <div
                            key={style.id}
                            onClick={() => handleSelectStyle(style)}
                            style={{
                                padding: '10px',
                                cursor: 'pointer',
                                background: selectedStyle && selectedStyle.id === style.id ? '#f0f0f1' : 'transparent',
                                borderBottom: '1px solid #eee',
                            }}
                        >
                            <strong>{style.title}</strong>
                            <div style={{ fontSize: '12px', color: '#666', marginTop: '4px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                {style.prompt}
                            </div>
                        </div>
                    ))}
                    {styles.length === 0 && <p style={{ color: '#888', fontStyle: 'italic' }}>{__('No styles created yet.', 'personaliseit')}</p>}
                </div>
            </div>

            {/* Editor Area */}
            <div style={{ flex: 1, overflowY: 'auto' }}>
                {notice && (
                    <Notice status={notice.status} onRemove={() => setNotice(null)}>
                        {notice.text}
                    </Notice>
                )}

                {!selectedStyle ? (
                    <div style={{ color: '#666', marginTop: '50px', textAlign: 'center' }}>
                        {__('Select a style to edit or create a new one.', 'personaliseit')}
                    </div>
                ) : (
                    <Card>
                        <CardHeader>
                            <div style={{ display: 'flex', justifyContent: 'space-between', width: '100%', alignItems: 'center' }}>
                                <h2 style={{ margin: 0 }}>
                                    {selectedStyle.id === 'new' ? __('Create New Style', 'personaliseit') : __('Edit Style', 'personaliseit')}
                                </h2>
                                <Button isPrimary onClick={handleSave} isBusy={isSaving} isSmall>
                                    {__('Save', 'personaliseit')}
                                </Button>
                            </div>
                        </CardHeader>
                        <CardBody>
                            <TextControl
                                label={__('Style Label (Visible to Customer)', 'personaliseit')}
                                value={selectedStyle.title}
                                onChange={(val) => setSelectedStyle({ ...selectedStyle, title: val })}
                                placeholder="e.g. Anime, Realistic, Sketch"
                            />

                            <TextareaControl
                                label={__('Prompt Suffix (Hidden)', 'personaliseit')}
                                help={__('This text will be appended to the user\'s prompt to enforce the style.', 'personaliseit')}
                                value={selectedStyle.prompt}
                                onChange={(val) => setSelectedStyle({ ...selectedStyle, prompt: val })}
                                rows={4}
                                placeholder="e.g. in anime style, studio ghibli, vibrant colors, cel shaded"
                                style={{ marginBottom: '20px' }}
                            />

                            <CheckboxControl
                                label={__('Remove Background', 'personaliseit')}
                                help={__('Attempt to remove the background from the generated image.', 'personaliseit')}
                                checked={selectedStyle.remove_bg}
                                onChange={(val) => setSelectedStyle({ ...selectedStyle, remove_bg: val })}
                            />

                            <div className="style-preview-control" style={{ marginBottom: '20px' }}>
                                <label className="components-base-control__label">{__('Preview Image', 'personaliseit')}</label>
                                <div style={{ display: 'flex', alignItems: 'flex-start', gap: '15px', marginTop: '5px' }}>
                                    {selectedStyle.preview_url && (
                                        <div style={{ border: '1px solid #ddd', padding: '5px', borderRadius: '4px', background: '#fff' }}>
                                            <img
                                                src={selectedStyle.preview_url}
                                                alt="Style Preview"
                                                style={{ maxWidth: '150px', maxHeight: '150px', objectFit: 'cover', display: 'block' }}
                                            />
                                        </div>
                                    )}
                                    <div style={{ flex: 1 }}>
                                        <FormFileUpload
                                            accept="image/*"
                                            onChange={handleImageUpload}
                                            isBusy={isUploading}
                                        >
                                            <Button isSecondary isBusy={isUploading}>
                                                {isUploading ? __('Uploading...', 'personaliseit') : (selectedStyle.preview_url ? __('Change Image', 'personaliseit') : __('Upload Image', 'personaliseit'))}
                                            </Button>
                                        </FormFileUpload>
                                        {selectedStyle.preview_url && (
                                            <Button
                                                isSecondary
                                                onClick={async () => {
                                                    setIsUploading(true);
                                                    try {
                                                        const { default: BackgroundRemovalService } = await import('../../common/services/BackgroundRemovalService');
                                                        const blob = await BackgroundRemovalService.removeBackground(selectedStyle.preview_url);
                                                        const file = new File([blob], "removed-bg.png", { type: "image/png" });

                                                        // Re-upload processed image
                                                        const formData = new FormData();
                                                        formData.append('file', file);
                                                        const response = await apiFetch({
                                                            path: '/wp/v2/media',
                                                            method: 'POST',
                                                            body: formData,
                                                        });
                                                        setSelectedStyle({ ...selectedStyle, preview_url: response.source_url });
                                                        setNotice({ status: 'success', text: __('Background removed successfully!', 'personaliseit') });
                                                    } catch (err) {
                                                        console.error(err);
                                                        setNotice({ status: 'error', text: __('Background removal failed: ', 'personaliseit') + err.message });
                                                    } finally {
                                                        setIsUploading(false);
                                                    }
                                                }}
                                                disabled={isUploading}
                                                style={{ marginLeft: '10px' }}
                                            >
                                                {__('Remove Background (AI)', 'personaliseit')}
                                            </Button>
                                        )}
                                        <p className="description" style={{ marginTop: '5px' }}>
                                            {__('Upload an image to represent this style in the selector.', 'personaliseit')}
                                        </p>
                                        <TextControl
                                            label={__('Or Image URL', 'personaliseit')}
                                            value={selectedStyle.preview_url}
                                            onChange={(val) => setSelectedStyle({ ...selectedStyle, preview_url: val })}
                                            placeholder="https://..."
                                            style={{ marginTop: '10px' }}
                                        />
                                    </div>
                                </div>
                            </div>

                            <div style={{ marginTop: '30px', display: 'flex', gap: '10px' }}>
                                <Button isPrimary onClick={handleSave} isBusy={isSaving}>
                                    {__('Save Style', 'personaliseit')}
                                </Button>
                                {selectedStyle.id !== 'new' && (
                                    <Button isDestructive isLink onClick={handleDelete}>
                                        {__('Delete Style', 'personaliseit')}
                                    </Button>
                                )}
                            </div>
                        </CardBody>
                    </Card>
                )}
            </div>
        </div >
    );
};

export default StyleManager;
