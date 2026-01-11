
/**
 * Finds the logical "Target Image" in the DOM where the Design Canvas should be overlaid.
 * Handles various Gallery/Slider implementations (WooCommerce, Flatsome, FlexSlider, Flickity).
 * 
 * @returns {HTMLElement|null} The target image element or wrapper.
 */
export const findTargetImage = () => {
    // Priority 1: Helper class explicitly identifying the main product image
    let target = document.querySelector('.personaliseit-target-image');

    // Priority 2: Flatsome / Flickity active slide IMG
    if (!target) target = document.querySelector('.flickity-slider > .is-selected img');
    if (!target) target = document.querySelector('.flickity-slider > .is-selected .zoomImg'); // EasyZoom

    // Priority 3: WooCommerce Standard Gallery active
    if (!target) target = document.querySelector('.woocommerce-product-gallery__image.is-selected img');

    // Priority 4: Generic FlexSlider Active
    if (!target) target = document.querySelector('.flex-active-slide img');

    // Priority 5: Fallback to the wrapper if IMG not found inside
    if (!target) target = document.querySelector('.woocommerce-product-gallery__image.is-selected');
    if (!target) target = document.querySelector('.woocommerce-product-gallery__image--placeholder');

    // Deep fallback
    if (!target) target = document.querySelector('.woocommerce-product-gallery__image'); // First image

    // Ultimate fallback: Main product image standard WP class
    if (!target) target = document.querySelector('.wp-post-image');

    return target;
};

/**
 * Finds the Gallery Container to observe for slide changes.
 */
export const findGalleryContainer = () => {
    return document.querySelector('.woocommerce-product-gallery') ||
        document.querySelector('.flickity-slider') ||
        document.querySelector('.flex-viewport');
};
