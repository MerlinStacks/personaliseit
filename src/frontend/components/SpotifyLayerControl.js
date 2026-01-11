import { __ } from '@wordpress/i18n';
import { useState, useEffect, useRef } from '@wordpress/element';

/**
 * SpotifyLayerControl - Enhanced Spotify code input with inline UI.
 * 
 * Features:
 * - Always-visible input (no button press required)
 * - Spotify branding with green accent
 * - Song details display (title, artist) - fetched from API or using defaults
 * - Respects backend color and metadata settings
 * - Simple background removal for Spotify codes
 */

/**
 * Remove background from Spotify code image using canvas.
 * Removes the specified color (default black) for transparency.
 */
const removeColorBackground = async (imageData, colorToRemove = 'black') => {
    return new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'Anonymous';

        img.onload = () => {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);

                const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imgData.data;

                // Threshold for color detection
                const threshold = 40;

                for (let i = 0; i < data.length; i += 4) {
                    const r = data[i], g = data[i + 1], b = data[i + 2];

                    if (colorToRemove === 'black') {
                        // Remove dark pixels
                        if (r < threshold && g < threshold && b < threshold) {
                            data[i + 3] = 0;
                        }
                    } else if (colorToRemove === 'white') {
                        // Remove light pixels
                        if (r > 255 - threshold && g > 255 - threshold && b > 255 - threshold) {
                            data[i + 3] = 0;
                        }
                    }
                }

                ctx.putImageData(imgData, 0, 0);
                resolve(canvas.toDataURL('image/png'));
            } catch (e) {
                console.error('Background removal failed:', e);
                resolve(imageData);
            }
        };

        img.onerror = () => resolve(imageData);
        img.src = imageData;
    });
};

/**
 * Parse Spotify URL/URI to extract components.
 */
const parseSpotifyInput = (input) => {
    if (!input) return null;
    const trimmed = input.trim();

    // Spotify URI format
    const uriMatch = trimmed.match(/^spotify:(track|album|artist|playlist):([a-zA-Z0-9]+)$/);
    if (uriMatch) {
        return { uri: trimmed, type: uriMatch[1], id: uriMatch[2] };
    }

    // Spotify URL format
    const urlMatch = trimmed.match(/open\.spotify\.com\/(track|album|artist|playlist)\/([a-zA-Z0-9]+)/);
    if (urlMatch) {
        return { uri: `spotify:${urlMatch[1]}:${urlMatch[2]}`, type: urlMatch[1], id: urlMatch[2] };
    }

    return null;
};

const SpotifyLayerControl = ({
    layer,
    userInputs,
    updateInput,
    handleAddToCart,
    labelPosition = 'above'
}) => {
    const [inputValue, setInputValue] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');
    const [songDetails, setSongDetails] = useState(null);
    const [previewImage, setPreviewImage] = useState(null);

    // Track if we've already generated for this URI to prevent loops
    const lastGeneratedUri = useRef('');
    const isGenerating = useRef(false);

    // Backend settings
    const showTitle = layer.spotifyShowTitle !== false; // Default true if not set
    const showArtist = layer.spotifyShowArtist !== false;
    const barColor = (layer.spotifyBarColor || '#ffffff').replace('#', '');
    const textColor = layer.spotifyTextColor || '#ffffff';

    // Determine layout
    const labelOrder = labelPosition === 'below' ? 2 : 0;
    const inputOrder = 1;

    /**
     * Fetch actual song/playlist metadata from Spotify API.
     */
    const fetchSpotifyMetadata = async (parsed) => {
        try {
            const restUrl = window.personaliseitData?.restUrl || '/wp-json/';
            const nonce = window.personaliseitData?.nonce;

            const metadataUrl = `${restUrl}personaliseit/v1/spotify/metadata?uri=${encodeURIComponent(parsed.uri)}`;
            const res = await fetch(metadataUrl, {
                headers: nonce ? { 'X-WP-Nonce': nonce } : {}
            });

            if (!res.ok) {
                return null;
            }

            const data = await res.json();

            // If empty name, return null to use defaults
            if (!data.name) {
                return null;
            }

            return {
                name: data.name,
                artist: data.artist,
                type: data.type
            };
        } catch (e) {
            console.error('Failed to fetch Spotify metadata:', e);
            return null;
        }
    };

    /**
     * Generate Spotify code from input.
     */
    const handleGenerate = async (input) => {
        const parsed = parseSpotifyInput(input);
        if (!parsed) {
            setError(__('Invalid Spotify URL or URI.', 'personaliseit'));
            return;
        }

        // Prevent duplicate generations
        if (isGenerating.current || lastGeneratedUri.current === parsed.uri) {
            return;
        }

        isGenerating.current = true;
        lastGeneratedUri.current = parsed.uri;

        setError('');
        setIsLoading(true);

        try {
            const restUrl = window.personaliseitData?.restUrl || '/wp-json/';
            const nonce = window.personaliseitData?.nonce;

            // Fetch the Spotify code with the correct bar color from backend settings
            const codeUrl = `${restUrl}personaliseit/v1/spotify/code?uri=${encodeURIComponent(parsed.uri)}&color=${barColor}&bg=000000`;
            const res = await fetch(codeUrl, {
                headers: nonce ? { 'X-WP-Nonce': nonce } : {}
            });

            if (!res.ok) {
                const errData = await res.json().catch(() => ({}));
                throw new Error(errData.message || __('Failed to generate code', 'personaliseit'));
            }

            const data = await res.json();
            let imageData = data.data; // Base64 data

            // Apply background removal (remove black background)
            if (imageData && imageData.startsWith('data:')) {
                imageData = await removeColorBackground(imageData, 'black');
            }

            // Try to fetch real metadata from Spotify API
            const metadata = await fetchSpotifyMetadata(parsed);

            // Determine song details to display
            const typeLabels = {
                track: __('Song', 'personaliseit'),
                album: __('Album', 'personaliseit'),
                artist: __('Artist', 'personaliseit'),
                playlist: __('Playlist', 'personaliseit')
            };

            // Priority: API metadata > Admin defaults > Generic label
            let title = metadata?.name || layer.spotifyDefaultTitle || '';
            let artist = metadata?.artist || layer.spotifyDefaultArtist || '';

            // If no defaults set and no API access, use descriptive placeholder
            if (!title && !artist) {
                title = typeLabels[parsed.type] || parsed.type;
                artist = __('Spotify Code', 'personaliseit');
            } else if (!title) {
                title = typeLabels[parsed.type] || parsed.type;
            } else if (!artist) {
                artist = typeLabels[parsed.type];
            }

            setSongDetails({
                title,
                artist,
                type: parsed.type
            });

            setPreviewImage(imageData);
            updateInput(layer.id, imageData);
            handleAddToCart();
        } catch (e) {
            console.error('Spotify code generation failed:', e);
            setError(e.message || __('Failed to fetch Spotify code.', 'personaliseit'));
            lastGeneratedUri.current = ''; // Allow retry on error
        } finally {
            setIsLoading(false);
            isGenerating.current = false;
        }
    };

    // Handle input change with debounce
    useEffect(() => {
        if (!inputValue) return;

        const parsed = parseSpotifyInput(inputValue);
        if (!parsed) return;

        // Don't re-generate if already done for this URI
        if (lastGeneratedUri.current === parsed.uri) return;

        const timer = setTimeout(() => {
            handleGenerate(inputValue);
        }, 800);

        return () => clearTimeout(timer);
    }, [inputValue, barColor]); // eslint-disable-line react-hooks/exhaustive-deps

    // Initialize input from layer default (once)
    useEffect(() => {
        if (layer.spotifyUri && !inputValue) {
            setInputValue(layer.spotifyUri);
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // Check if metadata section should be shown
    const shouldShowMetadata = showTitle || showArtist;

    return (
        <div
            className="pi-layer-group pi-spotify-layer"
            style={{
                marginBottom: '15px',
                background: 'linear-gradient(135deg, #191414 0%, #282828 100%)',
                borderRadius: '12px',
                padding: '16px',
                border: '1px solid #333'
            }}
        >
            {/* Header with Spotify branding */}
            <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '12px' }}>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="#1DB954">
                    <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z" />
                </svg>
                <label style={{
                    order: labelOrder,
                    fontWeight: '600',
                    color: '#fff',
                    fontSize: '14px'
                }}>
                    {layer.label || __('Spotify Code', 'personaliseit')}
                    {layer.required && <span style={{ color: '#1DB954', marginLeft: '4px' }}>*</span>}
                </label>
            </div>

            {/* Input field - always visible */}
            <div style={{ order: inputOrder, width: '100%' }}>
                <div style={{ position: 'relative' }}>
                    <input
                        type="text"
                        className="pi-modern-input"
                        value={inputValue}
                        onChange={(e) => {
                            setInputValue(e.target.value);
                            setError('');
                            // Reset generation tracking when user types new value
                            const newParsed = parseSpotifyInput(e.target.value);
                            if (!newParsed || newParsed.uri !== lastGeneratedUri.current) {
                                lastGeneratedUri.current = ''; // Allow new generation
                                setSongDetails(null);
                                setPreviewImage(null);
                            }
                        }}
                        placeholder={__('Paste Spotify link here...', 'personaliseit')}
                        style={{
                            width: '100%',
                            padding: '12px 40px 12px 16px',
                            borderRadius: '24px',
                            border: '2px solid #333',
                            background: '#121212',
                            color: '#fff',
                            fontSize: '14px',
                            transition: 'border-color 0.2s',
                            outline: 'none'
                        }}
                        onFocus={(e) => e.target.style.borderColor = '#1DB954'}
                        onBlur={(e) => e.target.style.borderColor = '#333'}
                    />
                    {isLoading && (
                        <div style={{
                            position: 'absolute',
                            right: '12px',
                            top: '50%',
                            transform: 'translateY(-50%)'
                        }}>
                            <span
                                className="dashicons dashicons-update"
                                style={{
                                    color: '#1DB954',
                                    animation: 'spin 1s linear infinite'
                                }}
                            />
                        </div>
                    )}
                </div>

                {/* Helper text */}
                <p style={{
                    fontSize: '11px',
                    color: '#b3b3b3',
                    margin: '8px 0 0 16px'
                }}>
                    {__('Paste a Spotify link (track, album, artist, or playlist)', 'personaliseit')}
                </p>
            </div>

            {/* Error message */}
            {error && (
                <div style={{
                    color: '#f15e6c',
                    fontSize: '12px',
                    marginTop: '8px',
                    padding: '8px 12px',
                    background: 'rgba(241, 94, 108, 0.1)',
                    borderRadius: '8px'
                }}>
                    {error}
                </div>
            )}

            {/* Preview and song details */}
            {previewImage && (
                <div style={{
                    marginTop: '16px',
                    padding: '12px',
                    background: '#121212',
                    borderRadius: '8px',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '12px'
                }}>
                    {/* Code preview */}
                    <div style={{
                        width: '80px',
                        height: '24px',
                        borderRadius: '4px',
                        overflow: 'hidden',
                        flexShrink: 0,
                        background: '#282828'
                    }}>
                        <img
                            src={previewImage}
                            alt="Spotify Code"
                            style={{
                                width: '100%',
                                height: '100%',
                                objectFit: 'contain'
                            }}
                        />
                    </div>

                    {/* Song details - only show if enabled in backend */}
                    {shouldShowMetadata && songDetails && (
                        <div style={{ flex: 1, minWidth: 0 }}>
                            {showTitle && (
                                <div style={{
                                    color: '#ffffff',
                                    fontSize: '14px',
                                    fontWeight: '600',
                                    whiteSpace: 'nowrap',
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis'
                                }}>
                                    {songDetails.title}
                                </div>
                            )}
                            {showArtist && (
                                <div style={{
                                    color: '#b3b3b3',
                                    fontSize: '12px',
                                    whiteSpace: 'nowrap',
                                    overflow: 'hidden',
                                    textOverflow: 'ellipsis'
                                }}>
                                    {songDetails.artist}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Success indicator */}
                    <div style={{
                        width: '24px',
                        height: '24px',
                        borderRadius: '50%',
                        background: '#1DB954',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        flexShrink: 0
                    }}>
                        <span className="dashicons dashicons-yes" style={{ color: '#000', fontSize: '16px' }} />
                    </div>
                </div>
            )}

            {/* CSS for spin animation */}
            <style>{`
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                .pi-spotify-layer input::placeholder {
                    color: #666;
                }
            `}</style>
        </div>
    );
};

export default SpotifyLayerControl;
