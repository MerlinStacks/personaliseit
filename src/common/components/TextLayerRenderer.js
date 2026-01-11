import React, { memo } from 'react';
import { Text, TextPath, Group } from 'react-konva';
import useImage from 'use-image';
import { applyTextTransform, getEmbroideryPattern } from '../../utils/canvasUtils';

// Helper for measure context
let measureCtx = null;
const getMeasureContext = () => {
    if (!measureCtx) {
        const canvas = document.createElement('canvas');
        measureCtx = canvas.getContext('2d');
    }
    return measureCtx;
};
const measureTextWidth = (text, font) => {
    const ctx = getMeasureContext();
    ctx.font = font;
    return ctx.measureText(text).width;
};

const TextLayerRenderer = memo(({ layer, textOverride, styleOverride, personalisationMethod, embroideryColor, isEditing, exportMode }) => {
    // If editing, we might want to hide the visual text so the textarea can be seen clearly?
    // Or we keep it as a reference? Usually hiding it avoids "double text" effect.
    if (isEditing) return null;

    const rawText = textOverride || layer.label || 'Text';
    const text = applyTextTransform(rawText, layer.textTransform);
    const style = styleOverride || {};

    const [patternImage] = useImage(layer.fillPatternImage || undefined, 'anonymous');

    // --- COLOR & PATTERN LOGIC ---
    let fill = (layer.fillPatternImage && patternImage) ? null : (style.color || layer.color || 'black');
    let finalFillPattern = (layer.fillPatternImage && patternImage) ? patternImage : null;

    let shadowBlur = 0;
    let shadowColor = 'transparent';
    let shadowOffsetX = 0;
    let shadowOffsetY = 0;

    // Skip effects if exporting
    if (!exportMode) {
        if (personalisationMethod === 'engraving') {
            fill = '#4a4a4a';
        } else if (personalisationMethod === 'embroidery') {
            const baseColor = embroideryColor || (typeof fill === 'string' ? fill : '#000000');
            finalFillPattern = getEmbroideryPattern(baseColor);
            fill = null;
            shadowBlur = 2;
            shadowColor = 'rgba(0,0,0,0.5)';
            shadowOffsetX = 1;
            shadowOffsetY = 1;
        } else {
            // Standard Shadows
            if (layer.hasShadow || style.hasShadow) {
                shadowBlur = layer.shadowBlur || 5;
                shadowColor = layer.shadowColor || '#000';
                shadowOffsetX = 5;
                shadowOffsetY = 5;
            }
        }
    }

    // --- FONT LOGIC ---
    // Auto-fit logic logic moved to component or kept? 
    // FrontendCanvas uses extensive auto-fit logic. Admin didn't use it explicitly in the snippet?
    // Admin snippet used `measureTextWidth` for bridge/bulge but not auto-size for standard text? 
    // Wait, Admin `handleTransformEnd` updates fontSize. Frontend renders READ ONLY.
    // For Unified Renderer, we should respect the `fontSize` in the layer prop. 
    // The Auto-Fitting in Frontend is a "Display Time" adjustment because users type in inputs.
    // Ideally, the Store updates the fontSize if text gets too long, OR the renderer handles shrinking.
    // Let's adopt the Frontend's logic which is "safer" for ensuring text fits.

    const minFontSize = layer.minFontSize || 10;
    const baseFontSize = parseInt(layer.fontSize || 30, 10);
    const maxFontSize = layer.maxSize ? parseInt(layer.maxSize, 10) : baseFontSize;

    const fontFamily = style.fontFamily || layer.fontFamily || 'Arial';
    const align = layer.align || 'center';
    const width = layer.width || 200;

    let fontSize = maxFontSize;
    const fontCtx = `${layer.fontStyle || 'normal'} ${maxFontSize}px "${fontFamily}"`;
    const measuredWidth = measureTextWidth(text, fontCtx);

    if (measuredWidth > width) {
        const ratio = width / measuredWidth;
        fontSize = Math.floor(maxFontSize * ratio);
        if (fontSize < minFontSize) fontSize = minFontSize;
    }

    const stroke = style.strokeWidth > 0 ? (style.stroke || '#000000') : (layer.stroke || null);
    const strokeWidth = style.strokeWidth > 0 ? (style.strokeWidth || 0) : (layer.strokeWidth || 0);

    // --- WARP LOGIC ---
    const warpStyle = style.warpStyle || layer.warpStyle || (layer.isCurved ? 'arc' : 'none');

    if (warpStyle !== 'none') {
        const amount = style.warpAmount !== undefined ? style.warpAmount : (layer.warpAmount || 50);
        let pathData = '';

        if (warpStyle === 'arc') {
            const curveStrength = amount * 1.5;
            const yBase = fontSize * 1.5;
            pathData = `M 0,${yBase} Q ${width / 2},${yBase - curveStrength} ${width},${yBase}`;
        } else if (warpStyle === 'bridge') {
            // Basic TextPath cant do Real Bridge (top/bottom separate). 
            // Admin component executed manual char positioning for bridge/bulge.
            // Frontend used simple Q curve for bridge/bulge?
            // Checking Frontend Code... Yes, it reused TextPath logic for bridge/bulge with slight tweaks.
            // Checking Admin Code... It did manual char placement!
            // **DECISION**: Use Admin's manual placement for Bridge/Bulge as it is superior?
            // Or keep it simple? The Admin manual placement allows true envelope distortion.
            // However, that is expensive and complex to port perfectly if dependencies differ.
            // Let's stick to TextPath for ARC, and maybe manual for others if needed.
            // For V1 of Unified, let's use the TextPath approach from Frontend as it is faster to implement/robust.
            const curveStrength = amount;
            const yBase = fontSize * 1.5;
            pathData = `M 0,${yBase} Q ${width / 2},${yBase - curveStrength} ${width},${yBase}`;
        } else if (warpStyle === 'bulge') {
            const yBase = fontSize * 1.5;
            pathData = `M 0,${yBase} Q ${width / 2},${yBase - amount} ${width},${yBase}`;
        }

        return (
            <TextPath
                text={text}
                fill={fill}
                fillPatternImage={finalFillPattern}
                fillPriority={finalFillPattern ? 'pattern' : 'color'}
                stroke={stroke}
                strokeWidth={strokeWidth}
                fontFamily={fontFamily}
                fontSize={fontSize}
                fontStyle={layer.fontStyle || 'normal'}
                align={align}
                width={width}
                data={pathData}
                shadowBlur={shadowBlur}
                shadowColor={shadowColor}
                shadowOffsetX={shadowOffsetX}
                shadowOffsetY={shadowOffsetY}
                lineHeight={layer.lineHeight || 1.2}
            />
        );
    }

    return (
        <Text
            text={text}
            fill={fill}
            fillPatternImage={finalFillPattern}
            fillPriority={finalFillPattern ? 'pattern' : 'color'}
            stroke={stroke}
            strokeWidth={strokeWidth}
            fontFamily={fontFamily}
            fontSize={fontSize}
            fontStyle={layer.fontStyle || 'normal'}
            align={align}
            width={width}
            padding={layer.padding || 0}
            lineHeight={layer.lineHeight || 1.2}
            shadowBlur={shadowBlur}
            shadowColor={shadowColor}
            shadowOffsetX={shadowOffsetX}
            shadowOffsetY={shadowOffsetY}
        />
    );
});

export default TextLayerRenderer;
