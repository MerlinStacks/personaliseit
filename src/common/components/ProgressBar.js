/**
 * ProgressBar - Animated progress indicator
 * 
 * Reusable progress bar component for async operations.
 * 
 * @module ProgressBar
 */

/**
 * Progress bar component
 * @param {Object} props - Component props
 * @param {number} props.progress - Progress percentage (0-100)
 * @param {string} [props.color='#2271b1'] - Bar color
 * @param {string} [props.height='6px'] - Bar height
 */
const ProgressBar = ({ progress, color = '#2271b1', height = '6px' }) => {
    if (progress <= 0) return null;

    return (
        <div
            className="pi-progress-bar"
            style={{ height }}
            role="progressbar"
            aria-valuenow={Math.round(progress)}
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                className="pi-progress-bar__fill"
                style={{
                    width: `${progress}%`,
                    background: color,
                    height: '100%',
                    transition: 'width 0.3s ease'
                }}
            />
        </div>
    );
};

export default ProgressBar;
