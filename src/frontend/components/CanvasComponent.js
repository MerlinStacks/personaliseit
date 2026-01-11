import { useRef, useEffect } from '@wordpress/element';
import { createPortal } from 'react-dom';
import useFrontendStore from '../store/useFrontendStore';
import FrontendCanvas from './FrontendCanvas';

const CanvasComponent = () => {
    const config = useFrontendStore((state) => state.config);
    const setStageRef = useFrontendStore((state) => state.setStageRef);
    const stageRef = useRef();

    useEffect(() => {
        if (stageRef) setStageRef(stageRef);
    }, [stageRef]);

    const portalTarget = document.getElementById('personaliseit-portal-overlay');

    if (!config) {
        if (!portalTarget) return null;
        return createPortal(
            <div className="personaliseit-canvas-wrapper personaliseit-skeleton" style={{ minHeight: '500px', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <span className="dashicons dashicons-format-image" style={{ fontSize: 64, color: '#ccc', opacity: 0.5 }}></span>
            </div>,
            portalTarget
        );
    }

    if (!portalTarget) {
        console.error('PersonaliseIt: Portal overlay not found');
        return null;
    }

    return createPortal(
        <div className="personaliseit-canvas-wrapper">
            <FrontendCanvas stageRef={stageRef} />
        </div>,
        portalTarget
    );
};

export default CanvasComponent;
