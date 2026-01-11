import React, { forwardRef, useImperativeHandle, useRef, useEffect } from 'react';
import { Group, Rect } from 'react-konva';
import Konva from 'konva';
import useImage from 'use-image';
import URLImage from './URLImage';
import TextLayerRenderer from './TextLayerRenderer';
import ImageLayerRenderer from './ImageLayerRenderer';

const DesignRenderer = forwardRef(({
    layers,
    userInputs,
    userStyles,
    personalisationMethod,
    embroideryColor,

    // Background / Overlay
    backgroundImage,
    overlayImage,

    // Canvas Props
    width,
    height,

    // Displacement
    displacementImage,
    displacementScale,

    // Interaction Handlers (Optional)
    onLayerClick,
    onLayerDragStart,
    onLayerDragMove,
    onLayerDragEnd,
    onLayerTransformEnd,
    onTextDblClick,

    // Edit State
    selectedLayerIds = [],
    editingLayerId = null,

    // Export Mode
    exportMode = false, // If true: hide background, overlay, distress, masks, filters

    // Children (e.g. Transformer)
    children
}, ref) => {

    const userLayersRef = useRef();

    // Load Displacement Map Image Object (Konva requirements)
    const [displacementMapObj] = useImage(displacementImage, 'anonymous');

    // --- DISPLACEMENT CACHE LOGIC ---
    useEffect(() => {
        if (userLayersRef.current && displacementMapObj) {
            // We must cache the group to apply the displacement filter properly
            try {
                userLayersRef.current.cache({
                    x: 0,
                    y: 0,
                    width: width,
                    height: height,
                    pixelRatio: 1 // Optimization: Keep ratio 1 for filters
                });
            } catch (e) {
                console.warn('DesignRenderer Cache Warn:', e);
            }
        } else if (userLayersRef.current) {
            userLayersRef.current.clearCache();
        }
    }, [displacementMapObj, layers, userInputs, userStyles, width, height]);

    return (
        <Group>
            {/* 1. Background Image (Hidden in Export Mode) */}
            {!exportMode && backgroundImage && (
                <URLImage
                    name="background-image"
                    src={backgroundImage}
                    width={width}
                    height={height} // Aspect ratio logic usually handled by parent Scale or Image Fit
                    // But here we assume `height` passed in is the calculated stage height or image height
                    listening={false}
                />
            )}

            {/* 2. Design Layers (Displaced Group) */}
            <Group
                ref={userLayersRef}
                filters={(!exportMode && displacementMapObj) ? [Konva.Filters.Displacement] : []}
                displacementMap={displacementMapObj}
                displacementScaleX={displacementScale || 20}
                displacementScaleY={displacementScale || 20}
            >
                {/* 
                   Wait, `useImage` inside a loop/conditional is bad. 
                   We should wrap the Group in a helper if we need hooks?
                   Better: Just use the loaded displacementMap logic if needed.
                   For now, I'll skip the internal useImage for displacement to keep it clean.
                   If displacement is needed, parent passes the `displacementMap` (Image Object).
                */}

                {layers.map((layer) => {
                    const isSelected = selectedLayerIds.includes(layer.id);
                    const isEditing = editingLayerId === layer.id;

                    // Skip Excluded Layers in Export Mode
                    if (exportMode && layer.excludeFromExport) return null;

                    // Merge Inputs
                    const text = userInputs ? userInputs[layer.id] : undefined;
                    const imageSrc = userInputs ? userInputs[layer.id] : undefined;
                    const style = userStyles ? userStyles[layer.id] : undefined;

                    const commonProps = {
                        key: layer.id,
                        id: layer.id,
                        x: layer.x,
                        y: layer.y,
                        width: layer.width,
                        height: layer.height,
                        scaleX: layer.scaleX || 1,
                        scaleY: layer.scaleY || 1,
                        rotation: layer.rotation || 0,
                        draggable: !!onLayerDragStart && !isEditing, // Only draggable if handler provided
                        onClick: (e) => onLayerClick && onLayerClick(e, layer.id),
                        onTap: (e) => onLayerClick && onLayerClick(e, layer.id),
                        onDragStart: onLayerDragStart,
                        onDragMove: onLayerDragMove,
                        onDragEnd: onLayerDragEnd,
                        onTransformEnd: (e) => onLayerTransformEnd && onLayerTransformEnd(e, layer),
                        globalCompositeOperation: exportMode ? 'source-over' : (layer.blendMode || 'source-over'),
                        opacity: exportMode ? 1 : (layer.opacity !== undefined ? layer.opacity : 1),
                        // Double click for text
                        onDblClick: (e) => layer.type === 'text' && onTextDblClick && onTextDblClick(e, layer),
                        onDblTap: (e) => layer.type === 'text' && onTextDblClick && onTextDblClick(e, layer),
                    };

                    if (layer.type === 'text') {
                        return (
                            <Group {...commonProps}>
                                <TextLayerRenderer
                                    layer={layer}
                                    textOverride={text}
                                    styleOverride={style}
                                    personalisationMethod={personalisationMethod}
                                    embroideryColor={embroideryColor}
                                    isEditing={isEditing}
                                    exportMode={exportMode}
                                />
                                {!exportMode && layer.distressImage && (
                                    <URLImage
                                        name="distress-image"
                                        src={layer.distressImage}
                                        width={layer.width}
                                        height={layer.height}
                                        globalCompositeOperation="destination-out"
                                    />
                                )}
                                {isSelected && !isEditing && (
                                    <Rect
                                        width={layer.width}
                                        height={layer.height}
                                        stroke="#007cba"
                                        dash={[5, 5]}
                                        listening={false}
                                    />
                                )}
                            </Group>
                        );
                    } else if (layer.type === 'image' || layer.type === 'clipart' || layer.type === 'spotify') {
                        return (
                            <Group {...commonProps}>
                                <ImageLayerRenderer
                                    layer={layer}
                                    imageOverride={
                                        layer.type === 'spotify' && imageSrc && !imageSrc.toString().includes('/')
                                            ? `/wp-json/personaliseit/v1/spotify/code?uri=${encodeURIComponent(imageSrc)}`
                                            : imageSrc
                                    }
                                    styleOverride={style}
                                    personalisationMethod={personalisationMethod}
                                    embroideryColor={embroideryColor}
                                    exportMode={exportMode}
                                />
                                {isSelected && (
                                    <Rect
                                        width={layer.width}
                                        height={layer.height}
                                        stroke={layer.type === 'clipart' ? '#e67e22' : '#27ae60'}
                                        dash={[5, 5]}
                                        listening={false}
                                    />
                                )}
                            </Group>
                        );
                    }
                    return null;
                })}
            </Group>

            {/* 3. Overlay Image (Hidden in Export Mode) */}
            {!exportMode && overlayImage && (
                <URLImage
                    name="overlay-image"
                    src={overlayImage}
                    width={width}
                    height={height}
                    listening={false}
                />
            )}

            {/* 4. Children (Transformer, etc.) */}
            {children}
        </Group>
    );
});

export default DesignRenderer;
