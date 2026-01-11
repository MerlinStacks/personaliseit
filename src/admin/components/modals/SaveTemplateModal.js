import { Modal, TextControl, Button, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';

const SaveTemplateModal = ({ isOpen, onClose, addNotice, exportConfig, captureSnapshot }) => {
    const [templateName, setTemplateName] = useState('');
    const [categories, setCategories] = useState([]);
    const [selectedCategory, setSelectedCategory] = useState('');
    const [isSaving, setIsSaving] = useState(false);

    useEffect(() => {
        if (isOpen) {
            apiFetch({ path: '/wp/v2/personaliseit_tpl_cat?per_page=100' })
                .then(cats => setCategories(cats))
                .catch(err => console.error('Error fetching categories', err));
        }
    }, [isOpen]);

    const handleSave = async () => {
        if (!templateName) return;
        setIsSaving(true);
        try {
            const config = exportConfig();

            // 1. Capture Snapshot (if function provided)
            let mediaId = 0;
            if (captureSnapshot) {
                const dataUrl = captureSnapshot();
                if (dataUrl) {
                    // Convert Data URL to Blob
                    const res = await fetch(dataUrl);
                    const blob = await res.blob();
                    const file = new File([blob], 'template-preview.jpg', { type: 'image/jpeg' });

                    const formData = new FormData();
                    formData.append('file', file);

                    try {
                        const mediaRes = await apiFetch({
                            path: '/wp/v2/media',
                            method: 'POST',
                            body: formData
                        });
                        mediaId = mediaRes.id;
                    } catch (e) {
                        console.error('Failed to upload thumbnail', e);
                    }
                }
            }

            // 2. Save Post
            const postData = {
                title: templateName,
                content: JSON.stringify(config),
                status: 'publish',
                featured_media: mediaId > 0 ? mediaId : undefined,
                personaliseit_tpl_cat: selectedCategory ? [parseInt(selectedCategory)] : []
            };

            await apiFetch({
                path: '/wp/v2/personaliseit_tpl',
                method: 'POST',
                data: postData,
            });
            addNotice(__('Template saved successfully!', 'personaliseit'), 'success');
            setTemplateName('');
            setSelectedCategory('');
            onClose();
        } catch (error) {
            console.error(error);
            addNotice(__('Failed to save template.', 'personaliseit'), 'error');
        } finally {
            setIsSaving(false);
        }
    };

    if (!isOpen) return null;

    return (
        <Modal
            title={__('Save Template', 'personaliseit')}
            onRequestClose={onClose}
        >
            <div style={{ padding: '0 10px 10px' }}>
                <TextControl
                    label={__('Template Name', 'personaliseit')}
                    value={templateName}
                    onChange={setTemplateName}
                    help={__('Enter a name for this design template.', 'personaliseit')}
                />

                {categories.length > 0 && (
                    <SelectControl
                        label={__('Category', 'personaliseit')}
                        value={selectedCategory}
                        options={[
                            { label: __('Select a Category', 'personaliseit'), value: '' },
                            ...categories.map(c => ({ label: c.name, value: c.id }))
                        ]}
                        onChange={setSelectedCategory}
                    />
                )}

                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 20, gap: 10 }}>
                    <Button isSecondary onClick={onClose} disabled={isSaving}>
                        {__('Cancel', 'personaliseit')}
                    </Button>
                    <Button isPrimary onClick={handleSave} disabled={!templateName || isSaving} isBusy={isSaving}>
                        {__('Save', 'personaliseit')}
                    </Button>
                </div>
            </div>
        </Modal>
    );
};

export default SaveTemplateModal;
