/**
 * ImageUploadDropzone - Drag and drop upload zone component
 * 
 * Renders the dropzone UI when no image is selected.
 * 
 * @module ImageUploadDropzone
 */
import { __ } from '@wordpress/i18n';

/**
 * Dropzone component for image uploads
 * @param {Object} props - Component props
 * @param {boolean} props.isDragging - Whether user is dragging over zone
 * @param {boolean} props.isUploading - Whether upload is in progress
 * @param {React.RefObject} props.inputRef - Ref to hidden file input
 * @param {Function} props.onDragOver - Drag over handler
 * @param {Function} props.onDragLeave - Drag leave handler
 * @param {Function} props.onDrop - Drop handler
 * @param {Function} props.onClick - Click handler
 * @param {Function} props.onFileChange - File input change handler
 */
const ImageUploadDropzone = ({
    isDragging,
    isUploading,
    inputRef,
    onDragOver,
    onDragLeave,
    onDrop,
    onClick,
    onFileChange
}) => {
    return (
        <>
            {/* Hidden File Input */}
            <input
                ref={inputRef}
                type="file"
                accept="image/*"
                className="pi-hidden-input"
                onChange={onFileChange}
                disabled={isUploading}
                aria-label={__('Upload image', 'personaliseit')}
            />

            <div
                className={`personaliseit-dropzone ${isDragging ? 'dragging' : ''}`}
                onDragOver={onDragOver}
                onDragLeave={onDragLeave}
                onDrop={onDrop}
                onClick={onClick}
                role="button"
                tabIndex={0}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        onClick();
                    }
                }}
                aria-label={__('Click or drag to upload image', 'personaliseit')}
            >
                {isUploading ? (
                    <p>{__('Uploading...', 'personaliseit')}</p>
                ) : (
                    <>
                        <span className="dashicons dashicons-upload" aria-hidden="true"></span>
                        <p>{__('Click or Drag to Upload Image', 'personaliseit')}</p>
                    </>
                )}
            </div>
        </>
    );
};

export default ImageUploadDropzone;
