import { useEffect, useRef, useLayoutEffect } from '@wordpress/element';
import usePersonaliseItStore from '../store/useFrontendStore';
import { findTargetImage, findGalleryContainer } from '../utils/domUtils';

// PORTAL STRATEGY: Overlay the canvas on top of the product image by appending to body
// and syncing position. This bypasses all theme overflow/clipping clipping.

const CanvasVisibilityManager = () => {
    const userInputs = usePersonaliseItStore((state) => state.userInputs);

    const config = usePersonaliseItStore((state) => state.config);

    // Show canvas if configuration is present.
    // This ensures that even "View-only" configs (backgrounds/overlays/filters) are displayed.
    const shouldShow = !!config;

    const overlayRef = useRef(null);
    const targetRef = useRef(null);
    const resizeObserverRef = useRef(null);
    const mutationObserverRef = useRef(null);

    // Get overlay reference once
    useEffect(() => {
        overlayRef.current = document.getElementById('personaliseit-portal-overlay');
        if (overlayRef.current) {
            // Initial Styles
            Object.assign(overlayRef.current.style, {
                position: 'absolute',
                zIndex: '2147483647', // Max Z-Index
                background: 'transparent',
                pointerEvents: 'none',
                display: 'none',
                overflow: 'visible',
                transformOrigin: 'top left' // Ensure scaling doesn't shift it
            });
        }
    }, []);

    // Core Sync Logic
    const syncPosition = () => {
        const overlay = overlayRef.current;
        const target = targetRef.current; // Use current tracked target

        if (!overlay || !target) return;

        // Visibility Check
        if (!shouldShow) {
            if (overlay.style.display !== 'none') overlay.style.display = 'none';
            // Restore opacity if we hid it
            if (target.style.opacity === '0') target.style.opacity = '';
            return;
        }

        const rect = target.getBoundingClientRect();

        // Safety: If target is invisible/collapsed
        if (rect.width <= 1 || rect.height <= 1) {
            if (overlay.style.display !== 'none') overlay.style.display = 'none';
            return;
        }

        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

        // Apply Position
        // Use fast DOM updates
        overlay.style.top = `${rect.top + scrollTop}px`;
        overlay.style.left = `${rect.left + scrollLeft}px`;
        overlay.style.width = `${rect.width}px`;
        overlay.style.height = `${rect.height}px`;

        if (overlay.style.display === 'none') overlay.style.display = 'block';
        overlay.style.pointerEvents = 'auto';

        // Hide Target (Visual Replacement)
        if (target.style.opacity !== '0') {
            target.style.opacity = '0';
        }
    };

    // Target Discovery & Observer Setup
    const refreshTarget = () => {
        const newTarget = findTargetImage();

        // If target changed
        if (newTarget !== targetRef.current) {
            // Restore old target visibility
            if (targetRef.current && targetRef.current.style.opacity === '0') {
                targetRef.current.style.opacity = '';
            }

            // Update ref
            targetRef.current = newTarget;

            // Re-connect ResizeObserver
            if (resizeObserverRef.current) resizeObserverRef.current.disconnect();

            if (newTarget) {
                if (!resizeObserverRef.current) {
                    resizeObserverRef.current = new ResizeObserver(() => syncPosition());
                }
                resizeObserverRef.current.observe(newTarget);
            }
        }

        syncPosition();
    };

    // 1. Visibility Change -> Sync
    useEffect(() => {
        syncPosition();
    }, [shouldShow]);

    // 2. Global Event Listeners (Scroll/Resize)
    useEffect(() => {
        const handleResize = () => syncPosition();
        const handleScroll = () => syncPosition(); // Throttle if needed, but native scroll is usually fine in modern browsers

        window.addEventListener('resize', handleResize, { passive: true });
        window.addEventListener('scroll', handleScroll, { passive: true });
        // Initial find
        refreshTarget();

        // 3. Gallery Mutation Observer (Find new target if slide changes)
        const gallery = findGalleryContainer() || document.body; // Fallback to body to catch lazy loads

        mutationObserverRef.current = new MutationObserver(() => {
            // Debounce slightly or just run?
            refreshTarget();
        });

        mutationObserverRef.current.observe(gallery, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style', 'src'] // Watch for class changes (active) or src changes
        });

        return () => {
            window.removeEventListener('resize', handleResize);
            window.removeEventListener('scroll', handleScroll);
            if (resizeObserverRef.current) resizeObserverRef.current.disconnect();
            if (mutationObserverRef.current) mutationObserverRef.current.disconnect();

            // Clean up visual state on unmount
            if (targetRef.current && targetRef.current.style.opacity === '0') {
                targetRef.current.style.opacity = '';
            }
            if (overlayRef.current) {
                overlayRef.current.style.display = 'none';
            }
        };
    }, []);

    // Backup Poller (Slow) - just in case MutationObserver misses edge cases (like pure JS animations without DOM attrib changes?)
    // Or if "Priority 1" target appears later.
    useEffect(() => {
        const interval = setInterval(refreshTarget, 1000);
        return () => clearInterval(interval);
    }, []);

    return null;
};
export default CanvasVisibilityManager;
