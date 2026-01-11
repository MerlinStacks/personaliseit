import { useState } from '@wordpress/element';
import { Button, Tooltip, Popover, ColorPicker } from '@wordpress/components';

export const CompactNumber = ({ label, value, onChange, min, max, step = 1 }) => (
    <div style={{ flex: 1, minWidth: '60px' }}>
        <div
            style={{
                fontSize: '10px',
                color: '#666',
                marginBottom: '2px',
                textTransform: 'uppercase',
            }}
        >
            {label}
        </div>
        <input
            type="number"
            value={Math.round(value)}
            onChange={(e) => onChange(parseFloat(e.target.value))}
            style={{
                width: '100%',
                padding: '4px',
                fontSize: '12px',
                border: '1px solid #ccc',
                borderRadius: '4px',
            }}
            min={min}
            max={max}
            step={step}
        />
    </div>
);

export const ColorControl = ({ label, color, onChange, compact = false }) => {
    const [isVisible, setIsVisible] = useState(false);
    return (
        <div style={compact ? {} : { marginBottom: '15px' }}>
            {label && (
                <div
                    style={{
                        fontSize: '10px',
                        color: '#666',
                        marginBottom: '2px',
                        textTransform: 'uppercase',
                    }}
                >
                    {label}
                </div>
            )}
            <div style={{ display: 'flex', alignItems: 'center' }}>
                <Tooltip text={color || 'Choose Color'}>
                    <Button
                        onClick={() => setIsVisible(!isVisible)}
                        style={{
                            width: '30px',
                            height: '30px',
                            borderRadius: '50%',
                            background: color || 'transparent',
                            border: '1px solid #ccc',
                            padding: 0,
                            minWidth: '30px',
                            boxShadow: 'inset 0 0 2px rgba(0,0,0,0.1)',
                        }}
                    />
                </Tooltip>
                {!compact && color && (
                    <span
                        style={{
                            marginLeft: '10px',
                            fontSize: '12px',
                            color: '#555',
                            fontFamily: 'monospace',
                        }}
                    >
                        {color}
                    </span>
                )}
            </div>
            {isVisible && (
                <Popover
                    position="bottom center"
                    onClose={() => setIsVisible(false)}
                >
                    <div style={{ padding: '10px' }}>
                        <ColorPicker
                            color={color}
                            onChangeComplete={(value) =>
                                onChange(value.hex)
                            }
                            disableAlpha
                        />
                    </div>
                </Popover>
            )}
        </div>
    );
};
