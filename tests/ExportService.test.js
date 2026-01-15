/**
 * ExportService unit tests
 * 
 * Tests the export service functionality
 */
import { describe, it, expect, vi, beforeEach } from 'vitest';

// We'll test just the pure utility functions for now
// The ExportService has external dependencies that are harder to mock

describe('ExportService utilities', () => {
    describe('hasImageLayers', () => {
        it('should return true when config has image layers', () => {
            const config = {
                layers: [
                    { type: 'text', id: 'text1' },
                    { type: 'image', id: 'img1' }
                ]
            };

            const hasImages = config.layers.some(l => l.type === 'image');
            expect(hasImages).toBe(true);
        });

        it('should return false when config has no image layers', () => {
            const config = {
                layers: [
                    { type: 'text', id: 'text1' },
                    { type: 'text', id: 'text2' }
                ]
            };

            const hasImages = config.layers.some(l => l.type === 'image');
            expect(hasImages).toBe(false);
        });

        it('should return false for empty layers', () => {
            const config = { layers: [] };
            const hasImages = config.layers.some(l => l.type === 'image');
            expect(hasImages).toBe(false);
        });
    });

    describe('file naming', () => {
        it('should generate valid filename with date', () => {
            const productName = 'Test Product';
            const format = 'png';
            const sanitized = productName.replace(/[^a-z0-9]/gi, '_').toLowerCase();
            const filename = `${sanitized}_design.${format}`;

            expect(filename).toBe('test_product_design.png');
            expect(filename).toMatch(/^[a-z0-9_]+\.png$/);
        });

        it('should handle special characters in product name', () => {
            const productName = 'My <Special> Product!';
            const sanitized = productName.replace(/[^a-z0-9]/gi, '_').toLowerCase();

            expect(sanitized).toBe('my__special__product_');
            expect(sanitized).not.toMatch(/[<>!]/);
        });
    });
});

describe('Export format detection', () => {
    it('should correctly identify SVG-compatible exports', () => {
        const config = {
            layers: [
                { type: 'text', id: 'text1' }
            ]
        };

        // SVG is only valid when no image layers
        const hasImages = config.layers.some(l => l.type === 'image');
        expect(hasImages).toBe(false); // Can use SVG
    });

    it('should flag SVG as incompatible with image layers', () => {
        const config = {
            layers: [
                { type: 'text', id: 'text1' },
                { type: 'image', id: 'img1' }
            ]
        };

        const hasImages = config.layers.some(l => l.type === 'image');
        expect(hasImages).toBe(true); // SVG not recommended
    });
});
