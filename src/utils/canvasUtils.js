/**
 * Shared Canvas Utilities for Personalise It!
 */

// --- CACHE ---
const patternCache = {};

/**
 * Convert Hex to RGB
 */
export const hexToRgb = (hex) => {
    const shorthandRegex = /^#?([a-f\d])([a-f\d])([a-f\d])$/i;
    hex = hex.replace(shorthandRegex, (m, r, g, b) => r + r + g + g + b + b);
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16)
    } : { r: 0, g: 0, b: 0 };
};

/**
 * Text Transformation Helper
 */
export const applyTextTransform = (text, transform) => {
    if (!text || !transform || transform === 'none') return text;
    switch (transform) {
        case 'uppercase':
            return text.toUpperCase();
        case 'lowercase':
            return text.toLowerCase();
        case 'capitalize':
            return text
                .split(' ')
                .map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase())
                .join(' ');
        default:
            return text;
    }
};

/**
 * Engraving Filter (Bayer Dithering)
 */
export const EngravingFilter = function (imageData) {
    const data = imageData.data;
    const nPixels = data.length;
    const width = imageData.width;

    // 4x4 Bayer Matrix
    const bayer = [
        0, 8, 2, 10,
        12, 4, 14, 6,
        3, 11, 1, 9,
        15, 7, 13, 5
    ];

    for (let i = 0; i < nPixels; i += 4) {
        // Calculate luminance (grayscale)
        const grayscale = data[i] * 0.3 + data[i + 1] * 0.59 + data[i + 2] * 0.11;

        // If pixel is mostly transparent, skip
        if (data[i + 3] < 10) continue;

        // Calculate x, y position
        const pixelIndex = i / 4;
        const x = pixelIndex % width;
        const y = Math.floor(pixelIndex / width);

        // Get threshold from Bayer Matrix (0-15) -> map to 0-255
        // Map 0-15 to 0-255: (val / 16) * 255
        const threshold = (bayer[(y % 4) * 4 + (x % 4)] / 16) * 255;

        if (grayscale < threshold) {
            // Dark enough to be engraved
            // Set to Engraving Color (Dark Grey)
            data[i] = 74;     // R
            data[i + 1] = 74; // G
            data[i + 2] = 74; // B
            data[i + 3] = 255; // Force opacity
        } else {
            // Make transparent (white part of image)
            data[i + 3] = 0;
        }
    }
};

/**
 * Get Embroidery Pattern Canvas
 */
export const getEmbroideryPattern = (color) => {
    if (patternCache[color]) return patternCache[color];

    const canvas = document.createElement('canvas');
    // Smaller pattern for tighter thread look
    const width = 4;
    const height = 4;
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');

    // 1. Base Darker Color (Shadow between threads)
    ctx.fillStyle = color;
    ctx.fillRect(0, 0, width, height);

    // Darken it a bit for the background/valleys
    ctx.fillStyle = 'rgba(0,0,0,0.2)';
    ctx.fillRect(0, 0, width, height);

    // 2. Draw the thread strand (Lighter/Base Color)
    // Diagonal from bottom-left to top-right
    ctx.strokeStyle = color;
    ctx.lineWidth = 3;
    ctx.lineCap = 'butt';
    ctx.beginPath();
    ctx.moveTo(-1, height + 1);
    ctx.lineTo(width + 1, -1);
    ctx.stroke();

    // 3. Highlight (Sheen)
    ctx.strokeStyle = 'rgba(255,255,255,0.4)';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(0, height);
    ctx.lineTo(width, 0);
    ctx.stroke();

    patternCache[color] = canvas;
    return canvas;
};

/**
 * Create Embroidery Filter for Images
 */
export const CreateEmbroideryFilter = (colorHex) => {
    const rgb = hexToRgb(colorHex);
    return function (imageData) {
        const data = imageData.data;
        const width = imageData.width;
        const nPixels = data.length;

        // Pattern Parameters
        const spacing = 4;

        for (let i = 0; i < nPixels; i += 4) {
            // Check Alpha
            if (data[i + 3] < 10) continue; // Skip transparent

            const pixelIndex = i / 4;
            const x = pixelIndex % width;
            const y = Math.floor(pixelIndex / width);

            // Diagonal Pattern: (x + y)
            const sum = x + y;
            const mod = sum % spacing; // 0, 1, 2, 3

            let factor = 1.0;
            if (mod === 0) {
                // Shadow (Valley)
                factor = 0.8;
            } else if (mod === 2) {
                // Highlight (Ridge)
                factor = 1.1;
            }
            // 1 and 3 are base color

            let r = rgb.r * factor;
            let g = rgb.g * factor;
            let b = rgb.b * factor;

            // Clamping
            if (r > 255) r = 255;
            if (g > 255) g = 255;
            if (b > 255) b = 255;

            data[i] = r;
            data[i + 1] = g;
            data[i + 2] = b;
            // Keep original alpha (shape)
        }
    };
};
