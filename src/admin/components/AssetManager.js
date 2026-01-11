import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
    Button,
    Spinner,
    FormFileUpload,
    Card,
    CardBody,
    TextControl,
    Modal,
    SelectControl,
    FormTokenField,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const AssetManager = () => {
    const [assetsData, setAssetsData] = useState({}); // { 'Category': [assets] }
    const [isLoading, setIsLoading] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [activeCategory, setActiveCategory] = useState('All');

    // Upload State
    const [isUploadModalOpen, setIsUploadModalOpen] = useState(false);
    const [uploadFile, setUploadFile] = useState(null);
    const [uploadCategories, setUploadCategories] = useState([]);
    const [uploadPrice, setUploadPrice] = useState(0);

    useEffect(() => {
        fetchAssets();
    }, []);

    const fetchAssets = async () => {
        setIsLoading(true);
        try {
            const results = await apiFetch({
                path: '/personaliseit/v1/assets',
            });

            // Organize flat list into categories
            const map = {};
            if (Array.isArray(results)) {
                results.forEach((asset) => {
                    const cats = (asset.categories && asset.categories.length > 0) ? asset.categories : ['Uncategorized'];
                    cats.forEach((cat) => {
                        if (!map[cat]) map[cat] = [];
                        map[cat].push(asset);
                    });
                });
            }
            setAssetsData(map);
        } catch (error) {
            console.error(error);
        } finally {
            setIsLoading(false);
        }
    };

    const handleFileSelect = (event) => {
        const files = Array.from(event.target.files);
        if (files.length === 0) return;

        // If "All" is active, default to "Uncategorized" or empty?
        // Better to force user to be in a category or select one.
        // For bulk, let's use the active category if not 'All', else 'Uncategorized'
        const initialCat = activeCategory === 'All' ? 'Uncategorized' : activeCategory;

        setUploadFile(files); // Now array
        setUploadCategories([initialCat]);
        setIsUploadModalOpen(true);
        event.target.value = ''; // Reset
    };

    const handleUploadSubmit = async () => {
        if (!uploadFile || uploadFile.length === 0) return;

        setIsUploading(true);
        const cats = uploadCategories.join(',');

        // Parallel or Serial? Serial is safer for server limits.
        const files = Array.isArray(uploadFile) ? uploadFile : [uploadFile];

        let successCount = 0;
        let failCount = 0;

        for (const file of files) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('title', file.name.replace(/\.[^/.]+$/, ''));
            formData.append('categories', cats);
            formData.append('price', uploadPrice);

            try {
                await apiFetch({
                    path: '/personaliseit/v1/assets',
                    method: 'POST',
                    body: formData,
                });
                successCount++;
            } catch (error) {
                console.error(error);
                failCount++;
            }
        }

        setIsUploading(false);
        setIsUploadModalOpen(false);
        setUploadFile(null);
        fetchAssets();

        if (failCount > 0) {
            alert(__('Upload complete with errors.', 'personaliseit') + ` Success: ${successCount}, Failed: ${failCount}`);
        } else {
            // Optional success notification?
        }
    };

    const handleDelete = async (id) => {
        if (!confirm(__('Are you sure you want to delete this asset?', 'personaliseit'))) return;

        try {
            await apiFetch({
                path: `/personaliseit/v1/assets/${id}`,
                method: 'DELETE',
            });
            fetchAssets();
        } catch (error) {
            console.error(error);
            alert(__('Delete failed', 'personaliseit'));
        }
    };

    // Flatten assets for "All" view or filter
    const getDisplayAssets = () => {
        if (activeCategory === 'All') {
            // Use a Set to avoid duplicates if an asset is in multiple categories
            const allAssets = new Map();
            Object.values(assetsData).forEach(arr => {
                arr.forEach(asset => allAssets.set(asset.id, asset));
            });
            return Array.from(allAssets.values());
        }
        return assetsData[activeCategory] || [];
    };

    const categories = Object.keys(assetsData).sort();

    if (isLoading && Object.keys(assetsData).length === 0) {
        return (
            <div className="asset-manager-loading">
                <Spinner />
                <p>{__('Loading clipart...', 'personaliseit')}</p>
            </div>
        );
    }

    return (
        <div className="asset-manager" style={{ display: 'flex', height: '100%', gap: '20px' }}>
            {/* Sidebar (Folders) */}
            <div className="asset-sidebar" style={{ width: '250px', flexShrink: 0 }}>
                <Card>
                    <div style={{ padding: '15px', borderBottom: '1px solid #eee' }}>
                        <h3 style={{ margin: 0 }}>{__('Categories', 'personaliseit')}</h3>
                    </div>
                    <div className="category-list">
                        <div
                            className={`category-item ${activeCategory === 'All' ? 'active' : ''}`}
                            onClick={() => setActiveCategory('All')}
                            style={{
                                padding: '10px 15px',
                                cursor: 'pointer',
                                background: activeCategory === 'All' ? '#f0f6fc' : 'transparent',
                                color: activeCategory === 'All' ? '#2271b1' : 'inherit',
                                fontWeight: activeCategory === 'All' ? 600 : 400,
                                display: 'flex', alignItems: 'center', gap: '8px'
                            }}
                        >
                            <span className="dashicons dashicons-images-alt2"></span>
                            {__('All Clipart', 'personaliseit')}
                        </div>
                        {categories.map(cat => (
                            <div
                                key={cat}
                                className={`category-item ${activeCategory === cat ? 'active' : ''}`}
                                onClick={() => setActiveCategory(cat)}
                                style={{
                                    padding: '10px 15px',
                                    cursor: 'pointer',
                                    background: activeCategory === cat ? '#f0f6fc' : 'transparent',
                                    color: activeCategory === cat ? '#2271b1' : 'inherit',
                                    fontWeight: activeCategory === cat ? 600 : 400,
                                    display: 'flex', alignItems: 'center', gap: '8px'
                                }}
                            >
                                <span className="dashicons dashicons-portfolio"></span>
                                {cat}
                            </div>
                        ))}
                    </div>
                </Card>
            </div>

            {/* Main Content */}
            <div className="asset-main" style={{ flex: 1 }}>
                <div className="asset-header" style={{ marginBottom: '20px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <div>
                        <h2 style={{ margin: '0 0 5px 0' }}>{activeCategory === 'All' ? __('Clipart Library', 'personaliseit') : activeCategory}</h2>
                        <p className="description" style={{ margin: 0 }}>
                            {__('Manage your clipart assets.', 'personaliseit')}
                        </p>
                    </div>

                    <FormFileUpload
                        accept="image/*"
                        onChange={handleFileSelect}
                        multiple={true}
                        render={({ openFileDialog }) => (
                            <Button
                                variant="primary"
                                onClick={openFileDialog}
                                icon="upload"
                            >
                                {__('Bulk Upload Clipart', 'personaliseit')}
                            </Button>
                        )}
                    />
                </div>

                {/* Drop Zone */}
                <div
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={(e) => {
                        e.preventDefault();
                        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                            // Map files to mock event target structure
                            handleFileSelect({ target: { files: e.dataTransfer.files } });
                        }
                    }}
                    style={{
                        padding: '20px',
                        border: '2px dashed #ccc',
                        borderRadius: '4px',
                        marginBottom: '20px',
                        textAlign: 'center',
                        color: '#666',
                        background: '#fafafa'
                    }}
                >
                    {__('Drag and drop files here to upload to current category', 'personaliseit')}
                </div>

                {getDisplayAssets().length === 0 ? (
                    <div className="asset-empty-state" style={{ padding: '40px', textAlign: 'center', background: '#fff', borderRadius: '4px', border: '1px solid #ddd' }}>
                        <p>{__('No clipart found in this category.', 'personaliseit')}</p>
                    </div>
                ) : (
                    <div className="asset-grid" style={{
                        display: 'grid',
                        gridTemplateColumns: 'repeat(auto-fill, minmax(150px, 1fr))',
                        gap: '15px'
                    }}>
                        {getDisplayAssets().map(asset => (
                            <Card key={asset.id} className="asset-card">
                                <CardBody style={{ padding: '0' }}>
                                    <div className="asset-preview" style={{
                                        height: '120px',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        background: '#f9f9f9',
                                        borderBottom: '1px solid #f0f0f0',
                                        padding: '10px'
                                    }}>
                                        <img
                                            src={asset.url}
                                            alt={asset.title}
                                            style={{
                                                maxWidth: '100%',
                                                maxHeight: '100%',
                                                objectFit: 'contain'
                                            }}
                                        />
                                    </div>
                                    <div className="asset-details" style={{ padding: '10px' }}>
                                        <div style={{ fontWeight: 500, marginBottom: '5px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                                            {asset.title}
                                        </div>
                                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                                            <span style={{ fontSize: '12px', color: '#666' }}>
                                                {asset.price > 0 ? `+$${asset.price}` : 'Free'}
                                            </span>
                                            <Button
                                                icon="trash"
                                                isDestructive
                                                isSmall
                                                onClick={() => handleDelete(asset.id)}
                                                label={__('Delete', 'personaliseit')}
                                            />
                                        </div>
                                    </div>
                                </CardBody>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {/* Upload Modal */}
            {isUploadModalOpen && (
                <Modal
                    title={__('Upload Clipart', 'personaliseit')}
                    onRequestClose={() => setIsUploadModalOpen(false)}
                >
                    <div style={{ padding: '0 10px 10px' }}>
                        <FormTokenField
                            label={__('Categories', 'personaliseit')}
                            value={uploadCategories}
                            suggestions={categories}
                            onChange={(tokens) => setUploadCategories(tokens)}
                            help={__('Enter new categories or select existing ones.', 'personaliseit')}
                        />

                        <TextControl
                            label={__('Extra Price ($)', 'personaliseit')}
                            type="number"
                            value={uploadPrice}
                            onChange={setUploadPrice}
                            min={0}
                            step={0.01}
                        />

                        <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '20px', gap: '10px' }}>
                            <Button isSecondary onClick={() => setIsUploadModalOpen(false)}>
                                {__('Cancel', 'personaliseit')}
                            </Button>
                            <Button isPrimary onClick={handleUploadSubmit} isBusy={isUploading}>
                                {__('Upload', 'personaliseit')}
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};

export default AssetManager;
