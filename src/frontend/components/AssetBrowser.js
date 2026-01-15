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
        <div className="pi-modal-overlay" onClick={onClose}>
            <div className="pi-modal" onClick={(e) => e.stopPropagation()}>
                <div className="pi-modal__header">
                    <h3>{__('Select Clipart', 'personaliseit')}</h3>
                    <button className="pi-modal__close" onClick={onClose}>
                        <span className="dashicons dashicons-no-alt" />
                    </button>
                </div>
                <div className="pi-modal__body">
                    <div className="pi-modal__sidebar">
                        {categories.map(cat => (
                            <button
                                key={cat}
                                className={`pi-category-btn ${activeCat === cat ? 'active' : ''}`}
                                onClick={() => setActiveCat(cat)}
                            >
                                {cat}
                            </button>
                        ))}
                    </div>
                    <div className="pi-modal__content">
                        <div className="pi-asset-grid">
                            {activeCat && assetsData[activeCat] && assetsData[activeCat].map(asset => (
                                <div
                                    key={asset.id}
                                    className="pi-asset-item"
                                    onClick={() => onSelect(asset)}
                                    title={asset.title}
                                >
                                    <div className="pi-asset-item__image">
                                        <img src={asset.url} alt={asset.title} />
                                    </div>
                                    <div className="pi-asset-item__title">{asset.title}</div>
                                    {asset.price > 0 && (
                                        <div className="pi-asset-item__price">+${asset.price}</div>
                                    )}
                                </div>
                            ))}
                        </div>

                        {isLoading && (
                            <div className="pi-loading">
                                <div className="pi-spinner" />
                                <span>{__('Loading...', 'personaliseit')}</span>
                            </div>
                        )}

                        {!isLoading && hasMore && (
                            <div className="pi-load-more">
                                <button className="pi-btn secondary" onClick={loadMore}>
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
