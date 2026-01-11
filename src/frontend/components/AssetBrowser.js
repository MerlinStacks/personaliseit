import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const AssetBrowser = ({ onSelect, onClose, allowedCategories = [] }) => {
    const [assetsData, setAssetsData] = useState({}); // { 'Cat': [assets] }
    const [categories, setCategories] = useState([]);
    const [activeCat, setActiveCat] = useState(null);
    const [isLoading, setIsLoading] = useState(false);

    // Pagination State
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(true);
    const PER_PAGE = 20;

    // Initial Load & Category Reset
    useEffect(() => {
        setPage(1);
        setAssetsData({});
        setHasMore(true);
        fetchAssets(1, true);
    }, [allowedCategories]); // Reload if allowedCategories prop changes

    const fetchAssets = (pageNum, isReset = false) => {
        setIsLoading(true);

        // Fetch with pagination
        apiFetch({
            path: `/personaliseit/v1/assets?page=${pageNum}&per_page=${PER_PAGE}`,
            parse: false
        })
            .then(res => {
                const totalPages = parseInt(res.headers.get('X-WP-TotalPages') || '1', 10);
                if (pageNum >= totalPages) setHasMore(false);
                return res.json();
            })
            .then(data => {
                const map = isReset ? {} : { ...assetsData };

                if (Array.isArray(data)) {
                    data.forEach((asset) => {
                        const cats = (asset.categories && asset.categories.length > 0) ? asset.categories : ['Uncategorized'];
                        cats.forEach((cat) => {
                            if (!map[cat]) map[cat] = [];
                            // Avoid duplicates if page overlapping or multi-cat
                            if (!map[cat].find(a => a.id === asset.id)) {
                                map[cat].push(asset);
                            }
                        });
                    });
                }

                setAssetsData(map);

                // Update Categories List
                let cats = Object.keys(map);
                if (allowedCategories && allowedCategories.length > 0) {
                    cats = cats.filter(c => allowedCategories.includes(c));
                }
                setCategories(cats);

                // Set initial active category if needed
                if (isReset && cats.length > 0) {
                    setActiveCat(cats[0]);
                } else if (!activeCat && cats.length > 0) {
                    setActiveCat(cats[0]);
                }

                setIsLoading(false);
            })
            .catch(err => {
                console.error(err);
                setIsLoading(false);
            });
    };

    const loadMore = () => {
        if (!isLoading && hasMore) {
            const nextPage = page + 1;
            setPage(nextPage);
            fetchAssets(nextPage, false);
        }
    };

    return (
        <div className="p-asset-browser-overlay" style={{
            position: 'fixed', top: 0, left: 0, right: 0, bottom: 0,
            background: 'rgba(0,0,0,0.5)', zIndex: 9999, display: 'flex',
            justifyContent: 'center', alignItems: 'center'
        }}>
            <div className="p-asset-browser-modal" style={{
                background: '#fff', width: '80%', height: '80%', maxWidth: '900px',
                borderRadius: '8px', display: 'flex', flexDirection: 'column', overflow: 'hidden'
            }}>
                <div className="p-header" style={{ padding: '15px', borderBottom: '1px solid #eee', display: 'flex', justifyContent: 'space-between' }}>
                    <h3>{__('Select Clipart', 'personaliseit')}</h3>
                    <button onClick={onClose} style={{ border: 'none', background: 'none', fontSize: '20px', cursor: 'pointer' }}>&times;</button>
                </div>
                <div className="p-body" style={{ flex: 1, display: 'flex', overflow: 'hidden' }}>
                    <div className="p-sidebar" style={{ width: '200px', borderRight: '1px solid #eee', padding: '10px', overflowY: 'auto' }}>
                        {categories.map(cat => (
                            <div key={cat}
                                onClick={() => setActiveCat(cat)}
                                style={{
                                    padding: '10px', cursor: 'pointer',
                                    background: activeCat === cat ? '#f0f0f0' : 'transparent',
                                    borderRadius: '4px', marginBottom: '5px'
                                }}
                            >
                                {cat}
                            </div>
                        ))}
                    </div>
                    <div className="p-grid-container" style={{ flex: 1, overflowY: 'auto', padding: '20px' }}>
                        <div className="p-grid" style={{ display: 'flex', flexWrap: 'wrap', gap: '15px', alignContent: 'flex-start' }}>
                            {activeCat && assetsData[activeCat] && assetsData[activeCat].map(asset => (
                                <div key={asset.id} className="p-asset-item"
                                    onClick={() => onSelect(asset)}
                                    style={{ width: '100px', cursor: 'pointer', textAlign: 'center' }}
                                    title={asset.title}
                                >
                                    <img src={asset.url} alt={asset.title} style={{ width: '100%', height: '100px', objectFit: 'contain', border: '1px solid #eee', padding: '5px' }} />
                                    <div style={{ fontSize: '12px', marginTop: '5px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{asset.title}</div>
                                    {asset.price > 0 && <div style={{ fontSize: '11px', color: 'green' }}>+${asset.price}</div>}
                                </div>
                            ))}
                        </div>

                        {isLoading && <div style={{ textAlign: 'center', padding: '20px' }}>Loading...</div>}

                        {!isLoading && hasMore && (
                            <div style={{ textAlign: 'center', marginTop: '20px' }}>
                                <button
                                    className="button button-secondary"
                                    onClick={loadMore}
                                    style={{ padding: '8px 16px', background: '#f0f0f0', border: '1px solid #ccc', borderRadius: '4px', cursor: 'pointer' }}
                                >
                                    {__('Load More Assets', 'personaliseit')}
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};
export default AssetBrowser;
