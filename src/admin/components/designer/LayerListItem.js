import { Tooltip } from '@wordpress/components';
import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const LayerListItem = ({
    layer,
    selectedId,
    onSelect,
    onDuplicate,
    onDelete,
    onUpdate,
    provided,
    snapshot,
    isDraggable,
}) => {
    const isSelected = selectedId === layer.id;
    const [isEditing, setIsEditing] = useState(false);
    const [tempLabel, setTempLabel] = useState(layer.label);
    const inputRef = useRef(null);

    // Sync label if external change happens
    useEffect(() => {
        setTempLabel(layer.label);
    }, [layer.label]);

    useEffect(() => {
        if (isEditing && inputRef.current) {
            inputRef.current.focus();
            inputRef.current.select();
        }
    }, [isEditing]);

    const handleSave = () => {
        if (tempLabel && tempLabel !== layer.label) {
            onUpdate(layer.id, { label: tempLabel });
        } else {
            setTempLabel(layer.label); // Revert if empty
        }
        setIsEditing(false);
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter') handleSave();
        if (e.key === 'Escape') {
            setTempLabel(layer.label);
            setIsEditing(false);
        }
        e.stopPropagation();
    };

    const rowStyle = {
        background: isSelected ? '#f0f6fc' : '#fff',
        borderLeft: isSelected ? '3px solid #2271b1' : '3px solid transparent',
        borderBottom: '1px solid #eee',
        padding: '8px 10px',
        display: 'flex',
        alignItems: 'center',
        cursor: 'pointer',
        transition: 'background 0.2s ease',
        height: '40px', // Fixed height for consistency
        ...provided.draggableProps.style,
    };

    return (
        <li
            ref={provided.innerRef}
            {...provided.draggableProps}
            {...provided.dragHandleProps}
            onClick={() => onSelect(layer.id)}
            style={rowStyle}
            className="pi-layer-list-item"
        >
            {/* 1. Drag Handle */}
            <span
                className="dashicons dashicons-menu"
                style={{
                    color: '#ccc',
                    marginRight: '8px',
                    cursor: 'grab',
                    fontSize: '16px',
                    opacity: 0.5
                }}
            />

            {/* 2. Type Icon */}
            <span
                className={`dashicons dashicons-${layer.type === 'text' ? 'editor-textcolor' : layer.type === 'clipart' ? 'images-alt2' : 'format-image'}`}
                style={{
                    color: isSelected ? '#2271b1' : '#555',
                    marginRight: '8px',
                    fontSize: '18px'
                }}
            />

            {/* 3. Label / Input */}
            <div style={{ flex: 1, minWidth: 0, marginRight: '10px' }}>
                {isEditing ? (
                    <input
                        ref={inputRef}
                        type="text"
                        value={tempLabel}
                        onChange={(e) => setTempLabel(e.target.value)}
                        onBlur={handleSave}
                        onKeyDown={handleKeyDown}
                        onClick={(e) => e.stopPropagation()}
                        style={{
                            width: '100%',
                            padding: '2px 4px',
                            fontSize: '13px',
                            border: '1px solid #2271b1',
                            borderRadius: '3px'
                        }}
                    />
                ) : (
                    <div
                        onDoubleClick={(e) => { e.stopPropagation(); setIsEditing(true); }}
                        style={{
                            fontSize: '13px',
                            fontWeight: isSelected ? 600 : 500,
                            color: '#1e1e1e',
                            whiteSpace: 'nowrap',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis'
                        }}
                    >
                        {layer.label || (layer.type === 'text' ? 'New Text' : 'Image Layer')}
                    </div>
                )}
            </div>

            {/* 4. Actions */}
            <div className="pi-row-actions" style={{ display: 'flex', gap: '4px', alignItems: 'center' }}>
                {!isEditing && (
                    <Tooltip text={__('Rename', 'personaliseit')}>
                        <button
                            className="pi-icon-button small"
                            onClick={(e) => { e.stopPropagation(); setIsEditing(true); }}
                            style={{ opacity: isSelected ? 1 : 0.5 }}
                        >
                            <span className="dashicons dashicons-edit" />
                        </button>
                    </Tooltip>
                )}

                <Tooltip text={__('Duplicate', 'personaliseit')}>
                    <button
                        className="pi-icon-button small"
                        onClick={(e) => { e.stopPropagation(); onDuplicate(layer.id); }}
                        style={{ opacity: isSelected ? 1 : 0.5 }}
                    >
                        <span className="dashicons dashicons-plus-alt2" />
                    </button>
                </Tooltip>

                <Tooltip text={__('Delete', 'personaliseit')}>
                    <button
                        className="pi-icon-button small danger"
                        onClick={(e) => { e.stopPropagation(); onDelete(layer.id); }}
                        style={{ opacity: isSelected ? 1 : 0.5, color: '#d63638' }}
                    >
                        <span className="dashicons dashicons-trash" />
                    </button>
                </Tooltip>
            </div>
        </li>
    );
};

export default LayerListItem;
