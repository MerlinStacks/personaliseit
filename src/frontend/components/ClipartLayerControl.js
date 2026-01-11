import { __ } from '@wordpress/i18n';

const ClipartLayerControl = ({
    layer,
    userInputs,
    setSelectedPlaceholderId,
    setShowAssets
}) => {
    return (
        <div className="control-group">
            <label>
                {layer.label || __('Clipart', 'personaliseit')}
                {layer.required && <span style={{ color: '#d63638', marginLeft: '3px' }}>*</span>}
            </label>
            <div>
                {userInputs[layer.id] && (
                    <div style={{ marginBottom: '5px' }}>
                        <img
                            src={userInputs[layer.id]}
                            alt="Selected"
                            style={{ maxHeight: '50px', border: '1px solid #ddd', padding: '2px' }}
                        />
                    </div>
                )}
                <button
                    type="button"
                    className="button"
                    onClick={() => {
                        setSelectedPlaceholderId(layer.id);
                        setShowAssets(true);
                    }}
                >
                    {userInputs[layer.id] ? __('Change Image', 'personaliseit') : __('Select Image', 'personaliseit')}
                </button>
            </div>
        </div>
    );
};

export default ClipartLayerControl;
