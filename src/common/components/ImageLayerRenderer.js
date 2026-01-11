import React, { memo } from 'react';
import { Group, Circle, Rect, Path } from 'react-konva';
import Konva from 'konva';
import URLImage from './URLImage';
import { EngravingFilter, CreateEmbroideryFilter } from '../../utils/canvasUtils';

const ImageLayerRenderer = memo(({ layer, imageOverride, styleOverride, personalisationMethod, embroideryColor, exportMode }) => {

    // Logic: Use override if present (Frontend Inputs), else layer src (Admin)
    // Actually Admin stores src in layer.image usually.
    // FrontendInputs is a map of ID -> URL.
    const src = imageOverride || layer.image || layer.src;

    if (!src) {
        // Render Placeholder if no image?
        // Admin renders dotted rect. Frontend renders nothing?
        // Let's render null if no src, unless keepPlaceholder prop?
        // For now: null. Admin can handle placeholder drawing separately or we add prop.
        if (layer.type === 'clipart') {
            // Clipart might have a placeholder text?
            return null;
        }
        return null;
    }

    const style = styleOverride || {};
    const filters = [];

    if (personalisationMethod === 'engraving') {
        filters.push(EngravingFilter);
    }

    if (personalisationMethod === 'embroidery' && !exportMode) {
        const stitchColor = embroideryColor || layer.color || '#000000';
        filters.push(CreateEmbroideryFilter(stitchColor));
    }

    // Standard Filters
    if (style.filter === 'grayscale') filters.push(Konva.Filters.Grayscale);
    if (style.filter === 'sepia') filters.push(Konva.Filters.Sepia);
    if (style.filter === 'invert') filters.push(Konva.Filters.Invert);

    const hasMask = !exportMode && style.maskShape && style.maskShape !== 'none';

    return (
        <Group
            ref={(node) => {
                if (node) {
                    if (hasMask) {
                        node.cache({ pixelRatio: 2 });
                    } else {
                        node.clearCache();
                    }
                }
            }}
        >
            <URLImage
                src={src}
                width={layer.width}
                height={layer.height}
                fit="contain"
                filters={filters}
            />

            {!exportMode && layer.maskImage && (
                <URLImage
                    name="mask-image"
                    src={layer.maskImage}
                    width={layer.width}
                    height={layer.height}
                    globalCompositeOperation="destination-in"
                />
            )}

            {/* Mask Shapes */}
            {style.maskShape === 'oval' && (
                <Circle
                    x={layer.width / 2}
                    y={layer.height / 2}
                    radius={Math.min(layer.width, layer.height) / 2}
                    fill="black"
                    scaleX={layer.width > layer.height ? layer.width / layer.height : 1}
                    scaleY={layer.height > layer.width ? layer.height / layer.width : 1}
                    globalCompositeOperation="destination-in"
                />
            )}
            {style.maskShape === 'circle' && (
                <Circle
                    x={layer.width / 2}
                    y={layer.height / 2}
                    radius={Math.min(layer.width, layer.height) / 2}
                    fill="black"
                    globalCompositeOperation="destination-in"
                />
            )}
            {style.maskShape === 'rounded_square' && (
                <Rect
                    x={0}
                    y={0}
                    width={layer.width}
                    height={layer.height}
                    cornerRadius={20}
                    fill="black"
                    globalCompositeOperation="destination-in"
                />
            )}
            {style.maskShape === 'heart' && (
                <Path
                    x={layer.width / 2}
                    y={layer.height / 2}
                    data="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"
                    fill="black"
                    offset={{ x: 12, y: 12 }}
                    scaleX={Math.min(layer.width, layer.height) / 24}
                    scaleY={Math.min(layer.width, layer.height) / 24}
                    globalCompositeOperation="destination-in"
                />
            )}

            {!exportMode && layer.distressImage && (
                <URLImage
                    name="distress-image"
                    src={layer.distressImage}
                    width={layer.width}
                    height={layer.height}
                    globalCompositeOperation="destination-out"
                />
            )}
        </Group>
    );
});

export default ImageLayerRenderer;
