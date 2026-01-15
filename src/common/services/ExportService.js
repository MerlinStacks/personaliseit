/**
 * ExportService - Centralized export functionality for PersonaliseIt
 * 
 * Handles PNG, JPG, PDF, SVG exports with single-view and multi-view support.
 * Uses JSZip for bundling multi-view raster exports.
 * 
 * @module ExportService
 */
import { PDFDocument } from 'pdf-lib';
import JSZip from 'jszip';

/**
 * Default export options
 */
const DEFAULT_OPTIONS = {
    pixelRatio: 4, // ~300 DPI for print quality
    jpegQuality: 0.92,
    filename: 'export'
};

/**
 * Download a data URI or blob URL as a file
 * @param {string} uri - Data URI or blob URL
 * @param {string} filename - Download filename
 */
export const downloadURI = (uri, filename) => {
    const link = document.createElement('a');
    link.download = filename;
    link.href = uri;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

/**
 * Convert a data URL to a Blob
 * @param {string} dataUrl - Base64 data URL
 * @returns {Blob} Blob representation
 */
const dataURLToBlob = (dataUrl) => {
    const [header, base64] = dataUrl.split(',');
    const mime = header.match(/:(.*?);/)[1];
    const binary = atob(base64);
    const array = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        array[i] = binary.charCodeAt(i);
    }
    return new Blob([array], { type: mime });
};

/**
 * Export a Konva stage as PNG
 * @param {Object} stageRef - React ref to Konva stage
 * @param {Object} options - Export options
 * @returns {string} Data URL of the exported image
 */
export const exportPNG = (stageRef, options = {}) => {
    const opts = { ...DEFAULT_OPTIONS, ...options };
    const stage = stageRef.current?.getStage?.() || stageRef.getStage?.();
    if (!stage) throw new Error('Invalid stage reference');
    
    return stage.toDataURL({ 
        pixelRatio: opts.pixelRatio,
        mimeType: 'image/png'
    });
};

/**
 * Export a Konva stage as JPG
 * @param {Object} stageRef - React ref to Konva stage
 * @param {Object} options - Export options
 * @returns {string} Data URL of the exported image
 */
export const exportJPG = (stageRef, options = {}) => {
    const opts = { ...DEFAULT_OPTIONS, ...options };
    const stage = stageRef.current?.getStage?.() || stageRef.getStage?.();
    if (!stage) throw new Error('Invalid stage reference');
    
    return stage.toDataURL({ 
        pixelRatio: opts.pixelRatio,
        mimeType: 'image/jpeg',
        quality: opts.jpegQuality
    });
};

/**
 * Export a Konva stage as PDF
 * @param {Object} stageRef - React ref to Konva stage
 * @param {Object} options - Export options
 * @returns {Promise<Blob>} PDF blob
 */
export const exportPDF = async (stageRef, options = {}) => {
    const opts = { ...DEFAULT_OPTIONS, ...options };
    const stage = stageRef.current?.getStage?.() || stageRef.getStage?.();
    if (!stage) throw new Error('Invalid stage reference');
    
    const pdfDoc = await PDFDocument.create();
    const imgData = stage.toDataURL({ pixelRatio: opts.pixelRatio });
    
    const imageBytes = await fetch(imgData).then(res => res.arrayBuffer());
    const pngImage = await pdfDoc.embedPng(imageBytes);
    
    const pdfWidth = stage.width() * opts.pixelRatio;
    const pdfHeight = stage.height() * opts.pixelRatio;
    
    const page = pdfDoc.addPage([pdfWidth, pdfHeight]);
    page.drawImage(pngImage, {
        x: 0,
        y: 0,
        width: pdfWidth,
        height: pdfHeight,
    });
    
    const pdfBytes = await pdfDoc.save();
    return new Blob([pdfBytes], { type: 'application/pdf' });
};

/**
 * Export multiple views as a multi-page PDF
 * @param {Array<{name: string, dataUrl: string, width: number, height: number}>} views - View data
 * @param {Object} options - Export options
 * @returns {Promise<Blob>} PDF blob
 */
export const exportMultiPagePDF = async (views, options = {}) => {
    const opts = { ...DEFAULT_OPTIONS, ...options };
    const pdfDoc = await PDFDocument.create();
    
    for (const view of views) {
        const imageBytes = await fetch(view.dataUrl).then(res => res.arrayBuffer());
        const pngImage = await pdfDoc.embedPng(imageBytes);
        
        const pdfWidth = view.width * opts.pixelRatio;
        const pdfHeight = view.height * opts.pixelRatio;
        
        const page = pdfDoc.addPage([pdfWidth, pdfHeight]);
        page.drawImage(pngImage, {
            x: 0,
            y: 0,
            width: pdfWidth,
            height: pdfHeight,
        });
    }
    
    const pdfBytes = await pdfDoc.save();
    return new Blob([pdfBytes], { type: 'application/pdf' });
};

/**
 * Export multiple views as a ZIP archive
 * @param {Array<{name: string, dataUrl: string}>} views - View data with name and dataUrl
 * @param {string} format - 'png' or 'jpg'
 * @param {Object} options - Export options
 * @returns {Promise<Blob>} ZIP blob
 */
export const exportZIP = async (views, format = 'png', options = {}) => {
    const zip = new JSZip();
    const ext = format === 'jpg' ? 'jpg' : 'png';
    
    for (const view of views) {
        const blob = dataURLToBlob(view.dataUrl);
        // Sanitize filename: replace spaces and special chars
        const safeName = view.name.replace(/[^a-z0-9]/gi, '_').toLowerCase();
        zip.file(`${safeName}.${ext}`, blob);
    }
    
    return await zip.generateAsync({ type: 'blob' });
};

/**
 * Check if any layers in the view contain customer-uploaded images
 * (Used to disable SVG export when images are present)
 * @param {Object} view - View object with layers
 * @param {Object} userInputs - User input map
 * @returns {boolean} True if images are present
 */
export const hasImageLayers = (view, userInputs = {}) => {
    if (!view?.layers) return false;
    
    return view.layers.some(layer => 
        (layer.type === 'image' || layer.type === 'clipart') &&
        !layer.excludeFromExport &&
        userInputs[layer.id]
    );
};

/**
 * Download and trigger file save for a single-view export
 * @param {Object} stageRef - React ref to Konva stage
 * @param {string} format - 'png', 'jpg', or 'pdf'
 * @param {string} filename - Base filename (without extension)
 * @param {Object} options - Export options
 */
export const downloadSingleView = async (stageRef, format, filename, options = {}) => {
    const ext = format === 'jpg' ? 'jpg' : format === 'pdf' ? 'pdf' : 'png';
    const fullFilename = `${filename}.${ext}`;
    
    if (format === 'pdf') {
        const blob = await exportPDF(stageRef, options);
        const url = URL.createObjectURL(blob);
        downloadURI(url, fullFilename);
        URL.revokeObjectURL(url);
    } else if (format === 'jpg') {
        const dataUrl = exportJPG(stageRef, options);
        downloadURI(dataUrl, fullFilename);
    } else {
        const dataUrl = exportPNG(stageRef, options);
        downloadURI(dataUrl, fullFilename);
    }
};

/**
 * Download all views as ZIP or multi-page PDF
 * @param {Array<{name: string, dataUrl: string, width: number, height: number}>} views - View data
 * @param {string} format - 'zip-png', 'zip-jpg', or 'pdf'
 * @param {string} filename - Base filename
 * @param {Object} options - Export options
 */
export const downloadAllViews = async (views, format, filename, options = {}) => {
    if (format === 'pdf') {
        const blob = await exportMultiPagePDF(views, options);
        const url = URL.createObjectURL(blob);
        downloadURI(url, `${filename}.pdf`);
        URL.revokeObjectURL(url);
    } else {
        const imgFormat = format === 'zip-jpg' ? 'jpg' : 'png';
        const blob = await exportZIP(views, imgFormat, options);
        const url = URL.createObjectURL(blob);
        downloadURI(url, `${filename}.zip`);
        URL.revokeObjectURL(url);
    }
};

export default {
    exportPNG,
    exportJPG,
    exportPDF,
    exportMultiPagePDF,
    exportZIP,
    downloadURI,
    downloadSingleView,
    downloadAllViews,
    hasImageLayers
};
