/**
 * Mock for @wordpress/i18n
 */
export const __ = (text) => text;
export const sprintf = (format, ...args) => {
    let i = 0;
    return format.replace(/%s/g, () => args[i++] || '');
};
export const _n = (single, plural, number) => (number === 1 ? single : plural);
export const _x = (text) => text;
