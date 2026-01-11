import opentype from 'opentype.js';

/**
 * Generate True Vector SVG from Konva Stage/Layers
 * 
 * @param {Object} stageData - The JSON representation of the stage or plain object with layers
 * @param {Object} options - Options { width, height, fonts }
 */
export const generateSVG = async (stageData, options) => {
    const width = options.width || 800;
    const height = options.height || 800;

    // Cache for loaded fonts
    const fontCache = {};

    const loadFont = async (fontFamily) => {
        // Strip quotes if present
        const cleanFamily = fontFamily.replace(/['"]/g, '');

        if (fontCache[cleanFamily]) return fontCache[cleanFamily];
        const fontConfig = options.fonts.find(f => f.family === cleanFamily);

        if (!fontConfig || !fontConfig.url) {
            console.warn(`Font config not found for family: ${cleanFamily}`);
            return null;
        }

        let url = fontConfig.url;

        // 1. Check for explicit TTF/OTF/WOFF in config
        if (fontConfig.files) {
            if (fontConfig.files.ttf) url = fontConfig.files.ttf;
            else if (fontConfig.files.otf) url = fontConfig.files.otf;
            else if (fontConfig.files.woff) url = fontConfig.files.woff;
        }

        // 2. Google Fonts Handling & WOFF2 Fallback
        // Google Fonts often serves WOFF2 by default. We need to fetch it as arrayBuffer.
        // Opentype.js DOES NOT support WOFF2.
        // If it's a Google Fonts CSS URL (e.g. fonts.googleapis.com/css2...), we can't parse that as a font buffer.
        // We'd need to fetch the CSS, parse the src url from it, and hope for a ttf/woff version.
        // Doing that client-side is hard due to User-Agent sniffing (Google serves woff2 to Chrome).

        // HOWEVER, if the user used our "Add Google Font" tool, we likely saved the CSS URL as the main URL.
        // If we are lucky, maybe we stored a direct file? No, we likely stored the CSS URL.
        // Realistically, for true vector export of Google Fonts client-side without a proxy, we are limited.
        // BUT, if we have a direct URL (like from our 'local' upload), fetch works.

        // Improvement: Try to fetch. If 404/Fail, return null.

        if (url.includes('.woff2') && !url.match(/\.(ttf|otf|woff)$/i)) {
            // Try to swap extension blindly if it's a file path
            const altUrl = url.replace('.woff2', '.ttf');
            try {
                const check = await fetch(altUrl, { method: 'HEAD' });
                if (check.ok) url = altUrl;
                else throw new Error('No alternative format');
            } catch (e) {
                console.warn(`Font ${cleanFamily} is WOFF2 and opentype.js cannot parse it. Outcome will be outlines-less.`);
                return null;
            }
        }

        try {
            const buffer = await fetch(url).then(res => {
                if (!res.ok) throw new Error(res.statusText);
                return res.arrayBuffer();
            });
            const font = opentype.parse(buffer);
            fontCache[cleanFamily] = font;
            return font;
        } catch (e) {
            console.error('Failed to load font for outlining:', cleanFamily, e);
            return null;
        }
    };

    let svgContent = '';

    // 0. (Removed Visibility Background)

    // 1. Defs (Patterns and Fonts)
    let defs = '';

    // Inject @font-face styles for better browser compatibility in fallback text mode
    let styleCSS = '';
    const usedFontFamilies = new Set();

    if (stageData.layers) {
        stageData.layers.forEach(layer => {
            if (layer.type === 'text' && layer.fontFamily) {
                // Determine family name
                let fam = layer.fontFamily;
                // Handle possible user-uploaded names vs system names
                // Just clean quotes for consistency
                fam = fam.replace(/['"]/g, '');
                usedFontFamilies.add(fam);
            }
        });
    }

    usedFontFamilies.forEach(family => {
        const fontConfig = options.fonts.find(f => f.family === family || f.family.replace(/['"]/g, '') === family);
        if (fontConfig) {
            let src = '';
            let hasFormat = false;

            if (fontConfig.files) {
                if (fontConfig.files.woff2) src += `url('${fontConfig.files.woff2}') format('woff2'),`;
                if (fontConfig.files.woff) src += `url('${fontConfig.files.woff}') format('woff'),`;
                if (fontConfig.files.ttf) src += `url('${fontConfig.files.ttf}') format('truetype'),`;
                if (fontConfig.files.otf) src += `url('${fontConfig.files.otf}') format('opentype'),`;
            } else if (fontConfig.url) {
                src += `url('${fontConfig.url}')`;
                // Guess format from extension if not explicit
                if (fontConfig.url.includes('.woff2')) src += " format('woff2')";
                else if (fontConfig.url.includes('.woff')) src += " format('woff')";
                else if (fontConfig.url.includes('.ttf')) src += " format('truetype')";
            }

            if (src.endsWith(',')) src = src.slice(0, -1);

            if (src) {
                styleCSS += `@font-face { font-family: '${family}'; src: ${src}; font-display: swap; }\n`;
            }
        }
    });

    if (styleCSS) {
        defs += `<style type="text/css">\n${styleCSS}\n<![CDATA[\n${styleCSS}\n]]>\n</style>`;
    }

    // 2. Traverse Layers
    if (stageData.layers) {
        // We need serial execution to handle async font loading
        for (const layer of stageData.layers) {

            // Text
            if (layer.type === 'text') {
                svgContent += await renderTextNode(layer, loadFont);
            }
            // Clipart / Images
            else if (layer.type === 'image' || layer.type === 'clipart') {
                svgContent += renderImageNode(layer);
            }
        }
    }

    return `<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
<defs>${defs}</defs>
${svgContent}
</svg>`;
};

const escapeXml = (unsafe) => {
    if (!unsafe) return "";
    return unsafe.replace(/[<>&'"]/g, function (c) {
        switch (c) {
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '&': return '&amp;';
            case '\'': return '&apos;';
            case '"': return '&quot;';
        }
    });
};

const renderTextNode = async (layer, loadFont) => {
    const fontFamily = layer.fontFamily || 'Arial';
    const font = await loadFont(fontFamily);
    const fontSize = Number(layer.fontSize) || 24;
    const fill = layer.color || '#000000';
    const text = layer.text || layer.label || '';

    let transformCmds = [];
    transformCmds.push(`translate(${Number(layer.x) || 0}, ${Number(layer.y) || 0})`);
    if (layer.rotation) transformCmds.push(`rotate(${Number(layer.rotation)})`);
    if (layer.scaleX || layer.scaleY) transformCmds.push(`scale(${Number(layer.scaleX) || 1}, ${Number(layer.scaleY) || 1})`);
    if (layer.offsetX || layer.offsetY) transformCmds.push(`translate(-${Number(layer.offsetX) || 0}, -${Number(layer.offsetY) || 0})`);

    const transform = transformCmds.length ? `transform="${transformCmds.join(' ')}"` : '';

    if (font) {
        // --- OUTLINE MODE ---
        try {
            const warpStyle = layer.warpStyle || (layer.isCurved ? 'arc' : 'none');

            if (warpStyle === 'none') {
                const lines = text.split('\n');
                const lineHeight = fontSize * (layer.lineHeight || 1);
                const padding = Number(layer.padding) || 0;

                // Use injected browser metrics if available for WIDTH
                const layerMetrics = options.textMetrics ? options.textMetrics[layer.id] : null;

                // Konva renders text with textBaseline = 'middle'.
                // It positions the line at y + lineHeight/2.
                // We need to calculate the Opentype (Alphabetic) Baseline relative to that Middle Baseline.
                // Metric Middle = (Ascender + Descender) / 2. (Note: Descender is negative)
                // Distance from Middle to Baseline = 0 - Middle.
                // Since SVG Y is Down, and Metric Up is Positive:
                // We need to add (Height_Above_Baseline) to go from Baseline to Top? No.
                // We are at Middle (visual Y). We want Baseline (visual Y + delta).
                // Baseline is BELOW Middle. So we must Add positive pixel value.
                // Middle Metric is usually "Above Baseline" (Positive).
                // So Y_baseline = Y_middle + (Metric_Middle * Scale).
                const metricMiddle = (font.ascender + font.descender) / 2;
                // The opentype.js coordinate system has Y-up. SVG has Y-down.
                // If metricMiddle is positive, it means the font's "middle" is above the baseline.
                // To get from Konva's middle (which is the visual middle) to the opentype baseline,
                // we need to move DOWN by this amount. So, we ADD the scaled metricMiddle.
                const baselineOffsetFromMiddle = (metricMiddle / font.unitsPerEm) * fontSize;

                // Correction: Konva's 'middle' approximation might just be 0.5 * em. 
                // Let's stick to true font metrics which usually works best for vector match.

                let pathData = '';
                const boxWidth = Number(layer.width) || 0;

                lines.forEach((line, i) => {
                    let lineWidth = 0;

                    if (layerMetrics && layerMetrics.lines[i]) {
                        lineWidth = layerMetrics.lines[i].width;
                    } else {
                        // Fallback with slight tracking
                        lineWidth = font.getAdvanceWidth(line, fontSize) * 1.015;
                    }

                    let xLine = padding;

                    if (layer.align === 'center') {
                        xLine = padding + ((boxWidth - (padding * 2)) - lineWidth) / 2;
                    } else if (layer.align === 'right') {
                        xLine = boxWidth - lineWidth - padding;
                    }

                    // Vertical Position Matching Konva
                    // 1. Find Top of Line Box
                    const topOfLine = padding + (i * lineHeight);
                    // 2. Find Middle of Line Box (where Konva anchors)
                    const middleOfLine = topOfLine + (lineHeight / 2);
                    // 3. Offset to Alphabetic Baseline
                    // Heuristic that matches most browsers/Konva:
                    // Middle is roughly 0.35em above baseline? 
                    // Let's try the metric derivation:
                    const yLine = middleOfLine + baselineOffsetFromMiddle;

                    const linePath = font.getPath(line, Number(xLine), Number(yLine), fontSize);
                    pathData += linePath.toPathData(2);
                });

                if (pathData && pathData.length > 5) {
                    return `<g ${transform}><path d="${pathData}" fill="${fill}" /></g>`;
                }


            }
        } catch (err) {
            console.error("Error generating outline, falling back to text:", err);
            // Fallthrough
        }
    }

    // --- FALLBACK (Standard SVG Text) ---

    // Calculate Anchor
    let textAnchor = 'start';
    let xBase = 0; // Relative to the group (which is at layer.x, layer.y)

    // In Konva, width is the box width.
    if (layer.align === 'center') {
        textAnchor = 'middle';
        xBase = (Number(layer.width) || 0) / 2;
    } else if (layer.align === 'right') {
        textAnchor = 'end';
        xBase = (Number(layer.width) || 0);
    }

    const lines = text.split('\n');
    const lineHeightPx = fontSize * (layer.lineHeight || 1.1);

    // Konva text y is top of box.
    // SVG text y is baseline of first line?
    // We'll mimic Konva: First line baseline is roughly fontSize away from top.

    const textSpans = lines.map((line, i) => {
        // xBase is the anchor position.
        // dy is relative to previous line.
        // For first line, we rely on the parent text y position.
        const dy = i === 0 ? 0 : lineHeightPx;

        // For subsequent lines, x must be reset to xBase because tspan x is absolute (?) 
        // No, tspan x can be absolute.
        return `<tspan x="${xBase}" dy="${dy}">${escapeXml(line)}</tspan>`;
    }).join('');

    // Curved Text (Simply Path)
    if (layer.warpStyle === 'arc') {
        const r = layer.radius || 100;
        const w = layer.width || 200;
        const pathData = `M 0,${layer.height} Q ${w / 2},${layer.height - r} ${w},${layer.height}`;
        const pathId = `path_${layer.id}`;
        return `<defs><path id="${pathId}" d="${pathData}" /></defs>
         <g ${transform}>
            <text font-family="${fontFamily}, Arial, sans-serif" font-size="${fontSize}" fill="${fill}" text-anchor="${textAnchor}">
               <textPath xlink:href="#${pathId}" startOffset="50%">${escapeXml(text)}</textPath>
            </text>
         </g>`;
    }

    // Standard Text Fallback
    // We use dominant-baseline="hanging" to Align the top of the text to y=0 (layer.y).
    // This removes the need to guess the baseline offset.
    return `<g ${transform}>
        <text y="0" dominant-baseline="hanging" alignment-baseline="hanging" font-family="${fontFamily}, Arial, sans-serif" font-size="${fontSize}" fill="${fill}" text-anchor="${textAnchor}" xml:space="preserve">${textSpans}</text>
    </g>`;
};

const renderImageNode = (layer) => {
    // ... (Same as before)
    const href = layer.image || layer.src || '';
    if (!href) return '';

    let transformCmds = [];
    transformCmds.push(`translate(${layer.x || 0}, ${layer.y || 0})`);
    if (layer.rotation) transformCmds.push(`rotate(${layer.rotation})`);
    if (layer.scaleX || layer.scaleY) transformCmds.push(`scale(${layer.scaleX || 1}, ${layer.scaleY || 1})`);

    const transform = transformCmds.length ? `transform="${transformCmds.join(' ')}"` : '';

    return `<g ${transform}>
        <image href="${href}" width="${layer.width}" height="${layer.height}" preserveAspectRatio="none" />
     </g>`;
};
