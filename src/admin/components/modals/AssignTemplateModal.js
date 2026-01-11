import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Modal for assigning a template to multiple products.
 * Uses a searchable multi-select dropdown (token field style).
 */
const AssignTemplateModal = ({ isOpen, onClose, templateId, templateName, onSuccess }) => {
    const [searchTerm, setSearchTerm] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [selectedProducts, setSelectedProducts] = useState([]);
    const [isSearching, setIsSearching] = useState(false);
    const [isAssigning, setIsAssigning] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);

    // Load currently assigned products when modal opens
    useEffect(() => {
        if (isOpen && templateId) {
            loadAssignedProducts();
        }
        // Reset states when closed
        if (!isOpen) {
            setSearchTerm('');
            setSearchResults([]);
            setError(null);
            setSuccess(null);
        }
    }, [isOpen, templateId]);

    /**
     * Fetch products already assigned to this template.
     */
    const loadAssignedProducts = async () => {
        try {
            const data = await apiFetch({
                path: `/personaliseit/v1/products/by-template/${templateId}`,
            });
            setSelectedProducts(data || []);
        } catch (err) {
            console.error('Failed to load assigned products:', err);
        }
    };

    /**
     * Search products by name.
     */
    useEffect(() => {
        const delayDebounce = setTimeout(() => {
            if (searchTerm.length >= 2) {
                searchProducts(searchTerm);
            } else {
                setSearchResults([]);
            }
        }, 300);
        return () => clearTimeout(delayDebounce);
    }, [searchTerm]);

    const searchProducts = async (term) => {
        setIsSearching(true);
        try {
            const results = await apiFetch({
                path: `/personaliseit/v1/products?search=${encodeURIComponent(term)}`,
            });
            // Filter out already selected products
            const selectedIds = selectedProducts.map(p => p.id);
            const filtered = results.filter(p => !selectedIds.includes(p.id));
            setSearchResults(filtered);
        } catch (err) {
            console.error('Search error:', err);
        } finally {
            setIsSearching(false);
        }
    };

    /**
     * Add a product to selection.
     */
    const addProduct = (product) => {
        if (!selectedProducts.find(p => p.id === product.id)) {
            setSelectedProducts([...selectedProducts, {
                id: product.id,
                title: product.title,
                is_variation: product.is_variation || false,
            }]);
        }
        setSearchTerm('');
        setSearchResults([]);
    };

    /**
     * Remove a product from selection.
     */
    const removeProduct = (productId) => {
        setSelectedProducts(selectedProducts.filter(p => p.id !== productId));
    };

    /**
     * Assign the template to all selected products.
     */
    const handleAssign = async () => {
        if (selectedProducts.length === 0) {
            setError(__('Please select at least one product.', 'personaliseit'));
            return;
        }

        setIsAssigning(true);
        setError(null);
        setSuccess(null);

        try {
            const result = await apiFetch({
                path: '/personaliseit/v1/products/assign-template',
                method: 'POST',
                data: {
                    template_id: templateId,
                    product_ids: selectedProducts.map(p => p.id),
                },
            });

            setSuccess(result.message);
            if (onSuccess) {
                onSuccess(result);
            }
        } catch (err) {
            setError(err.message || __('Failed to assign template.', 'personaliseit'));
        } finally {
            setIsAssigning(false);
        }
    };

    if (!isOpen) return null;

    return (
        <Modal
            title={__('Assign Template to Products', 'personaliseit')}
            onRequestClose={onClose}
            className="personaliseit-assign-modal"
            style={{ maxWidth: '600px' }}
        >
            <div style={{ padding: '10px 0' }}>
                <p style={{ marginTop: 0, color: '#666' }}>
                    {__('Assign', 'personaliseit')} <strong>"{templateName}"</strong> {__('to products:', 'personaliseit')}
                </p>

                {error && <Notice status="error" isDismissible={false}>{error}</Notice>}
                {success && <Notice status="success" isDismissible={false}>{success}</Notice>}

                {/* Selected Products Tags */}
                <div style={{ marginBottom: '15px', minHeight: '40px', padding: '8px', background: '#f9f9f9', borderRadius: '4px', border: '1px solid #ddd' }}>
                    {selectedProducts.length === 0 ? (
                        <span style={{ color: '#999' }}>{__('No products selected', 'personaliseit')}</span>
                    ) : (
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '5px' }}>
                            {selectedProducts.map(product => (
                                <span
                                    key={product.id}
                                    style={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: '5px',
                                        background: product.is_variation ? '#7c3aed' : '#0073aa',
                                        color: '#fff',
                                        padding: '4px 8px',
                                        borderRadius: '3px',
                                        fontSize: '12px',
                                    }}
                                >
                                    {product.title}
                                    <button
                                        type="button"
                                        onClick={() => removeProduct(product.id)}
                                        style={{
                                            background: 'none',
                                            border: 'none',
                                            color: '#fff',
                                            cursor: 'pointer',
                                            padding: 0,
                                            fontSize: '14px',
                                            lineHeight: 1,
                                        }}
                                    >
                                        ×
                                    </button>
                                </span>
                            ))}
                        </div>
                    )}
                </div>

                {/* Search Input */}
                <div style={{ position: 'relative', marginBottom: '15px' }}>
                    <input
                        type="text"
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        placeholder={__('Search products to add...', 'personaliseit')}
                        style={{
                            width: '100%',
                            padding: '10px',
                            border: '1px solid #ddd',
                            borderRadius: '4px',
                            fontSize: '14px',
                        }}
                    />

                    {/* Search Results Dropdown */}
                    {(searchResults.length > 0 || isSearching) && (
                        <div
                            style={{
                                position: 'absolute',
                                top: '100%',
                                left: 0,
                                right: 0,
                                background: '#fff',
                                border: '1px solid #ddd',
                                borderTop: 'none',
                                borderRadius: '0 0 4px 4px',
                                maxHeight: '200px',
                                overflowY: 'auto',
                                zIndex: 100,
                                boxShadow: '0 2px 10px rgba(0,0,0,0.1)',
                            }}
                        >
                            {isSearching ? (
                                <div style={{ padding: '10px', textAlign: 'center' }}>
                                    <Spinner />
                                </div>
                            ) : (
                                searchResults.map(product => (
                                    <div
                                        key={product.id}
                                        onClick={() => addProduct(product)}
                                        style={{
                                            padding: '10px',
                                            cursor: 'pointer',
                                            borderBottom: '1px solid #eee',
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: '10px',
                                            backgroundColor: product.is_variation ? '#f9f9ff' : '#fff',
                                        }}
                                        onMouseEnter={(e) => e.currentTarget.style.background = product.is_variation ? '#eeeeff' : '#f5f5f5'}
                                        onMouseLeave={(e) => e.currentTarget.style.background = product.is_variation ? '#f9f9ff' : '#fff'}
                                    >
                                        {product.image && (
                                            <img
                                                src={product.image}
                                                alt=""
                                                style={{ width: '30px', height: '30px', objectFit: 'cover', borderRadius: '3px' }}
                                            />
                                        )}
                                        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: '2px' }}>
                                            <span>{product.title}</span>
                                            <div style={{ display: 'flex', gap: '5px' }}>
                                                {product.is_variation && (
                                                    <span style={{ fontSize: '10px', background: '#7c3aed', color: '#fff', padding: '1px 5px', borderRadius: '3px' }}>
                                                        {__('Variation', 'personaliseit')}
                                                    </span>
                                                )}
                                                {product.type === 'variable' && !product.is_variation && (
                                                    <span style={{ fontSize: '10px', background: '#2563eb', color: '#fff', padding: '1px 5px', borderRadius: '3px' }}>
                                                        {__('Variable', 'personaliseit')}
                                                    </span>
                                                )}
                                                {product.has_config && (
                                                    <span style={{ fontSize: '10px', color: '#999' }}>
                                                        {__('(has config)', 'personaliseit')}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    )}
                </div>

                {/* Actions */}
                <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px', marginTop: '20px' }}>
                    <Button isSecondary onClick={onClose} disabled={isAssigning}>
                        {__('Cancel', 'personaliseit')}
                    </Button>
                    <Button isPrimary onClick={handleAssign} isBusy={isAssigning} disabled={isAssigning}>
                        {isAssigning ? __('Assigning...', 'personaliseit') : __('Assign Template', 'personaliseit')}
                    </Button>
                </div>
            </div>
        </Modal>
    );
};

export default AssignTemplateModal;
