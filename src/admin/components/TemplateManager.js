import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Spinner, Card, CardBody, Modal, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import useStore from '../store/useStore';
import AssignTemplateModal from './modals/AssignTemplateModal';

const TemplateManager = () => {
    const [templates, setTemplates] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [categories, setCategories] = useState([]);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [newTemplateTitle, setNewTemplateTitle] = useState('');

    // Assign modal state
    const [assignModalOpen, setAssignModalOpen] = useState(false);
    const [assignTemplateId, setAssignTemplateId] = useState(null);
    const [assignTemplateName, setAssignTemplateName] = useState('');

    // Global store actions
    const setTemplateId = useStore((state) => state.setTemplateId);

    useEffect(() => {
        fetchTemplates();
        fetchCategories();
    }, []);

    const fetchCategories = async () => {
        try {
            const cats = await apiFetch({ path: '/wp/v2/personaliseit_tpl_cat?per_page=100' });
            setCategories(cats);
        } catch (error) {
            console.error('Error loading categories', error);
        }
    }

    const fetchTemplates = async () => {
        setIsLoading(true);
        try {
            const posts = await apiFetch({ path: '/wp/v2/personaliseit_tpl?per_page=100&_embed' });
            setTemplates(posts);
        } catch (error) {
            console.error(error);
            alert(__('Failed to load templates.', 'personaliseit'));
        } finally {
            setIsLoading(false);
        }
    };

    const handleEdit = (id) => {
        const designerUrl = 'admin.php?page=personaliseit&template_id=' + id;
        window.location.href = designerUrl;
    };

    const handleDelete = async (id) => {
        if (!confirm(__('Are you sure you want to delete this template?', 'personaliseit'))) return;

        try {
            await apiFetch({
                path: `/wp/v2/personaliseit_tpl/${id}`,
                method: 'DELETE',
            });
            setTemplates(templates.filter(t => t.id !== id));
        } catch (error) {
            console.error(error);
            alert(__('Failed to delete template.', 'personaliseit'));
        }
    };

    const handleAssign = (template) => {
        setAssignTemplateId(template.id);
        setAssignTemplateName(template.title.rendered);
        setAssignModalOpen(true);
    };

    const handleCreate = async () => {
        if (!newTemplateTitle) return;

        try {
            const result = await apiFetch({
                path: '/wp/v2/personaliseit_tpl',
                method: 'POST',
                data: {
                    title: newTemplateTitle,
                    status: 'publish'
                }
            });
            handleEdit(result.id);
        } catch (error) {
            console.error(error);
            alert(__('Failed to create template.', 'personaliseit'));
        }
    };

    if (isLoading) {
        return (
            <div className="personaliseit-loading">
                <Spinner />
                <p>{__('Loading templates...', 'personaliseit')}</p>
            </div>
        );
    }

    return (
        <div className="template-manager">
            <div className="template-manager-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20 }}>
                <div>
                    <h2>{__('Design Templates', 'personaliseit')}</h2>
                    <p className="description">{__('Create templates and assign them to products.', 'personaliseit')}</p>
                </div>
                <Button variant="primary" onClick={() => setShowCreateModal(true)}>
                    {__('Create New Template', 'personaliseit')}
                </Button>
            </div>

            {templates.length === 0 ? (
                <div className="empty-state-card" style={{ textAlign: 'center', padding: 40, background: '#fff', border: '1px solid #ddd' }}>
                    <span className="dashicons dashicons-format-image" style={{ fontSize: 48, color: '#ccc', height: 48, width: 48 }}></span>
                    <h3>{__('No templates found', 'personaliseit')}</h3>
                    <p>{__('Create your first design template to get started.', 'personaliseit')}</p>
                    <Button variant="secondary" onClick={() => setShowCreateModal(true)} style={{ marginTop: 10 }}>
                        {__('Create Template', 'personaliseit')}
                    </Button>
                </div>
            ) : (
                <div className="template-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: 20 }}>
                    {templates.map(t => (
                        <Card key={t.id} style={{ overflow: 'hidden' }}>
                            <div className="template-preview" style={{ height: 150, background: '#f0f0f0', display: 'flex', alignItems: 'center', justifyContent: 'center', borderBottom: '1px solid #eee' }}>
                                {t._embedded && t._embedded['wp:featuredmedia'] && t._embedded['wp:featuredmedia'][0] ? (
                                    <img
                                        src={t._embedded['wp:featuredmedia'][0].source_url}
                                        alt={t.title.rendered}
                                        style={{ width: '100%', height: '100%', objectFit: 'contain' }}
                                    />
                                ) : (
                                    <span className="dashicons dashicons-format-image" style={{ color: '#ccc', fontSize: 32 }}></span>
                                )}
                            </div>
                            <CardBody>
                                <h3 style={{ margin: '0 0 10px 0', fontSize: 16, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                    {t.title.rendered}
                                </h3>
                                <div className="template-actions" style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                    <Button isPrimary isSmall onClick={() => handleEdit(t.id)}>
                                        {__('Edit', 'personaliseit')}
                                    </Button>
                                    <Button isSecondary isSmall onClick={() => handleAssign(t)} style={{ background: '#00a32a', color: '#fff', borderColor: '#00a32a' }}>
                                        {__('Assign', 'personaliseit')}
                                    </Button>
                                    <Button isDestructive isSmall onClick={() => handleDelete(t.id)}>
                                        {__('Delete', 'personaliseit')}
                                    </Button>
                                </div>
                            </CardBody>
                        </Card>
                    ))}
                </div>
            )}

            {showCreateModal && (
                <Modal title={__('Create New Template', 'personaliseit')} onRequestClose={() => setShowCreateModal(false)}>
                    <div style={{ minWidth: 300 }}>
                        <TextControl
                            label={__('Template Title', 'personaliseit')}
                            value={newTemplateTitle}
                            onChange={setNewTemplateTitle}
                            placeholder={__('e.g., Summer T-Shirt Design', 'personaliseit')}
                        />
                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10, marginTop: 20 }}>
                            <Button isSecondary onClick={() => setShowCreateModal(false)}>
                                {__('Cancel', 'personaliseit')}
                            </Button>
                            <Button isPrimary onClick={handleCreate} disabled={!newTemplateTitle}>
                                {__('Create', 'personaliseit')}
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}

            {/* Assign Template Modal */}
            <AssignTemplateModal
                isOpen={assignModalOpen}
                onClose={() => setAssignModalOpen(false)}
                templateId={assignTemplateId}
                templateName={assignTemplateName}
                onSuccess={() => {
                    // Optionally refresh or show success
                }}
            />
        </div>
    );
};

export default TemplateManager;
