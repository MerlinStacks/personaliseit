import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const DesignerToolbar = ({
    showGrid,
    setShowGrid,
    snapToGrid,
    setSnapToGrid,
    canvasRef
}) => {
    return (
        <div className="personaliseit-designer__toolbar">
            <Button
                icon="minus"
                onClick={() => canvasRef.current?.zoomOut()}
                label={__('Zoom Out', 'personaliseit')}
            />
            <Button
                icon="plus"
                onClick={() => canvasRef.current?.zoomIn()}
                label={__('Zoom In', 'personaliseit')}
            />
            <Button
                icon="fullscreen"
                onClick={() => canvasRef.current?.fitToScreen()}
                label={__('Fit to Screen', 'personaliseit')}
            />
            <div style={{ width: 1, height: 20, background: '#ccc', margin: '0 5px' }}></div>
            <Button
                icon="grid-view"
                isPressed={showGrid}
                onClick={() => setShowGrid(!showGrid)}
                label={__('Toggle Grid', 'personaliseit')}
            />
            <Button
                icon="align-center"
                isPressed={snapToGrid}
                onClick={() => setSnapToGrid(!snapToGrid)}
                label={__('Snap to Grid', 'personaliseit')}
            />
        </div>
    );
};

export default DesignerToolbar;
