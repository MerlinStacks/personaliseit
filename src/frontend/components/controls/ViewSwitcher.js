/**
 * ViewSwitcher - Unified component for switching views or variations
 * 
 * Renders a grid of thumbnail buttons for selecting views/variations.
 * Consolidates duplicate code from ControlsComponent.
 */
import { __ } from '@wordpress/i18n';

/**
 * Renders a grid of selectable items with optional thumbnails
 * @param {Object} props - Component props
 * @param {Array} props.items - Array of items to display ({id, name, image, thumbnail})
 * @param {string} props.currentId - Currently selected item ID
 * @param {Function} props.onSelect - Selection handler (id) => void
 * @param {string} props.label - Accessible label for the switcher
 * @param {string} props.className - Optional additional CSS class
 */
const ViewSwitcher = ({ items, currentId, onSelect, label, className = '' }) => {
    if (!items || items.length <= 1) return null;

    return (
        <div className={`personaliseit-view-switcher ${className}`}>
            <label>{label}</label>
            <div className="pi-switcher-grid">
                {items.map(item => {
                    const swatchImg = item.thumbnail || item.image?.src || item.image;
                    const isActive = currentId === item.id;

                    return (
                        <div
                            key={item.id}
                            className={`pi-switcher-item ${isActive ? 'active' : ''}`}
                            onClick={() => onSelect(item.id)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    onSelect(item.id);
                                }
                            }}
                            role="button"
                            tabIndex={0}
                            aria-pressed={isActive}
                            title={item.name}
                        >
                            {swatchImg ? (
                                <div className="pi-switcher-item__content">
                                    <img src={swatchImg} alt={item.name} />
                                    <span>{item.name}</span>
                                </div>
                            ) : (
                                <button
                                    type="button"
                                    className={`pi-btn ${isActive ? 'primary' : 'secondary'}`}
                                >
                                    {item.name}
                                </button>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

export default ViewSwitcher;
