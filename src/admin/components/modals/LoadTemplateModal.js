import { Modal, Button, Spinner, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';

const LoadTemplateModal = ({ isOpen, onClose, addNotice, importConfig }) => {
    const [templates, setTemplates] = useState([]);
    const [categories, setCategories] = useState([]);
    const [selectedCategory, setSelectedCategory] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        if (isOpen) {
            // Load Categories
            apiFetch({ path: '/wp/v2/personaliseit_tpl_cat?per_page=100' })
                .then(cats => setCategories(cats))
                .catch(err => console.error(err));

            fetchTemplates();
        }
    }, [isOpen]);

    useEffect(() => {
        if (isOpen) {
            fetchTemplates(selectedCategory);
        }
    }, [selectedCategory]);

    const fetchTemplates = async (catId = '') => {
        setIsLoading(true);
        try {
            let path = '/wp/v2/personaliseit_tpl?per_page=100&_embed';
            if (catId) path += `&personaliseit_tpl_cat=${catId}`;

            const posts = await apiFetch({ path });
            setTemplates(posts);
        } catch (error) {
            console.error(error);
            addNotice(__('Failed to load templates.', 'personaliseit'), 'error');
        } finally {
            setIsLoading(false);
        }
    };

    const handleLoad = (template) => {
        if (!window.confirm(__('This will overwrite your current design. Are you sure?', 'personaliseit'))) return;

        try {
            // Fetch single to ensure full content (though list often has content.raw, context=edit is safer)
            apiFetch({
                path: `/wp/v2/personaliseit_tpl/${template.id}?context=edit`,
            }).then((post) => {
                let config;
                try {
                    config = JSON.parse(post.content.raw);
                } catch (e) {
                    // Fallback if already parsed or simple string
                    config = post.content.raw;
                }

                // If content is just a string, try parsing it again just in case
                if (typeof config === 'string') {
                    try { config = JSON.parse(config); } catch (e) { }
                }

                if (config && config.views) {
                    importConfig(config);
                    onClose();
                } else {
                    addNotice(__('Invalid template data.', 'personaliseit'), 'error');
                }
            });
        } catch (e) {
            addNotice(__('Error loading template.', 'personaliseit'), 'error');
        }
    };

    if (!isOpen) return null;

    return (
        <Modal
            title={__('Load Template', 'personaliseit')}
            onRequestClose={onClose}
        >
            <div style={{ padding: '0 10px 10px' }}>

                {categories.length > 0 && (
                    <div style={{ marginBottom: 15 }}>
                        <SelectControl
                            label={__('Filter by Category', 'personaliseit')}
                            value={selectedCategory}
                            options={[
                                { label: __('All Categories', 'personaliseit'), value: '' },
                                ...categories.map(c => ({ label: c.name, value: c.id }))
                            ]}
                            onChange={setSelectedCategory}
                        />
                    </div>
                )}

                {isLoading ? (
                    <Spinner />
                ) : (
                    <div className="template-grid" style={{ display: 'flex', flexDirection: 'column', gap: 10, maxHeight: '400px', overflowY: 'auto' }}>
                        {templates.length === 0 ? (
                            <p>{__('No templates found.', 'personaliseit')}</p>
                        ) : (
                            templates.map((t) => (
                                <div
                                    key={t.id}
                                    style={{
                                        padding: '10px',
                                        border: '1px solid #ddd',
                                        borderRadius: 4,
                                        display: 'flex',
                                        justifyContent: 'space-between',
                                        alignItems: 'center',
                                        background: '#fff',
                                        marginBottom: '5px'
                                    }}
                                >
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                        {t._embedded && t._embedded['wp:featuredmedia'] && t._embedded['wp:featuredmedia'][0] ? (
                                            <img
                                                src={t._embedded['wp:featuredmedia'][0].source_url}
                                                alt={t.title.rendered}
                                                style={{ width: '60px', height: '60px', objectFit: 'cover', borderRadius: '4px', border: '1px solid #eee' }}
                                            />
                                        ) : (
                                            <div style={{ width: '60px', height: '60px', background: '#f0f0f0', display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: '4px' }}>
                                                <span className="dashicons dashicons-format-image" style={{ color: '#ccc' }}></span>
                                            </div>
                                        )}
                                        <strong style={{ fontSize: '1.1em' }}>{t.title.rendered}</strong>
                                    </div>
                                    <Button
                                        isSecondary
                                        isSmall
                                        onClick={() => handleLoad(t)}
                                    >
                                        {__('Load', 'personaliseit')}
                                    </Button>
                                </div>
                            ))
                        )}
                    </div>
                )}
                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 20 }}>
                    <Button isSecondary onClick={onClose}>
                        {__('Close', 'personaliseit')}
                    </Button>
                </div>
            </div>
        </Modal>
    );
};

export default LoadTemplateModal;
