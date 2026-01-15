/**
 * useValidationSync - Custom hook for syncing validation state to WooCommerce
 * 
 * Manages the disabled/enabled state of the WooCommerce add-to-cart button
 * based on PersonaliseIt validation errors.
 */
import { useEffect } from '@wordpress/element';

/**
 * Syncs validation state to the WooCommerce add-to-cart button
 * @param {Object} params - Hook parameters
 * @param {boolean} params.isValid - Whether all validations pass
 * @param {Array} params.errors - Array of error objects with message property
 */
const useValidationSync = ({ isValid, errors }) => {
    useEffect(() => {
        const addToCartBtn = document.querySelector(
            'form.cart button[type="submit"], form.cart .single_add_to_cart_button'
        );

        if (!addToCartBtn) return;

        if (!isValid) {
            addToCartBtn.disabled = true;
            addToCartBtn.style.opacity = '0.5';
            addToCartBtn.style.cursor = 'not-allowed';
            addToCartBtn.title = errors.map(e => e.message).join(', ');
        } else {
            addToCartBtn.disabled = false;
            addToCartBtn.style.opacity = '';
            addToCartBtn.style.cursor = '';
            addToCartBtn.title = '';
        }
    }, [isValid, errors]);
};

export default useValidationSync;
