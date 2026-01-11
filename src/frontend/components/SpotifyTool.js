import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * SpotifyTool component for generating Spotify codes.
 * 
 * Supports two modes:
 * 1. Direct URL/URI input (no API required) - Default mode
 * 2. Search mode (requires Spotify API credentials)
 * 
 * @param {Object} props Component props
 * @param {Function} props.onSelect Callback when a code is generated
 * @param {Function} props.onCancel Callback when cancelled
 */
const SpotifyTool = ({ onSelect, onCancel }) => {
    const [mode, setMode] = useState('paste'); // 'paste' or 'search'
    const [query, setQuery] = useState('');
    const [pasteUrl, setPasteUrl] = useState('');
    const [results, setResults] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');

    // Check if search mode is available (API credentials configured)
    const enableSpotify = window.personaliseitData?.settings?.enableSpotify;

    /**
     * Parse a Spotify URL or URI to extract the Spotify URI.
     * Supports formats:
     * - spotify:track:abc123
     * - https://open.spotify.com/track/abc123
     * - https://open.spotify.com/track/abc123?si=xxxxx
     * 
     * @param {string} input URL or URI string
     * @returns {Object|null} Parsed result with uri, type, and id, or null if invalid
     */
    const parseSpotifyInput = (input) => {
        if (!input) return null;

        const trimmed = input.trim();

        // Already a Spotify URI format (spotify:type:id)
        const uriMatch = trimmed.match(/^spotify:(track|album|artist|playlist):([a-zA-Z0-9]+)$/);
        if (uriMatch) {
            return {
                uri: trimmed,
                type: uriMatch[1],
                id: uriMatch[2]
            };
        }

        // Spotify URL format (open.spotify.com/type/id)
        const urlMatch = trimmed.match(/open\.spotify\.com\/(track|album|artist|playlist)\/([a-zA-Z0-9]+)/);
        if (urlMatch) {
            return {
                uri: `spotify:${urlMatch[1]}:${urlMatch[2]}`,
                type: urlMatch[1],
                id: urlMatch[2]
            };
        }

        return null;
    };

    /**
     * Generate Spotify code from pasted URL/URI (no API required).
     */
    const handlePasteGenerate = async () => {
        const parsed = parseSpotifyInput(pasteUrl);

        if (!parsed) {
            setError(__('Invalid Spotify URL or URI. Please paste a valid Spotify link.', 'personaliseit'));
            return;
        }

        setIsLoading(true);
        setError('');

        try {
            const restUrl = window.personaliseitData?.restUrl || '/wp-json/';
            const nonce = window.personaliseitData?.nonce;

            // Use the code proxy endpoint (no API key needed)
            const res = await fetch(`${restUrl}personaliseit/v1/spotify/code?uri=${encodeURIComponent(parsed.uri)}`, {
                headers: nonce ? { 'X-WP-Nonce': nonce } : {}
            });

            if (!res.ok) {
                const errData = await res.json();
                throw new Error(errData.message || 'Failed to generate code');
            }

            const data = await res.json();

            // Format type label for display
            const typeLabel = parsed.type.charAt(0).toUpperCase() + parsed.type.slice(1);

            onSelect({
                uri: parsed.uri,
                codeData: data.data, // Base64 image data
                name: typeLabel,
                artist: parsed.id, // Show ID as subtitle
                type: parsed.type
            });
        } catch (e) {
            console.error(e);
            setError(e.message);
        } finally {
            setIsLoading(false);
        }
    };

    /**
     * Search Spotify API (requires credentials).
     */
    const handleSearch = async () => {
        if (!query) return;
        setIsLoading(true);
        setError('');
        setResults([]);

        try {
            const restUrl = window.personaliseitData?.restUrl || '/wp-json/';
            const nonce = window.personaliseitData?.nonce;

            const res = await fetch(`${restUrl}personaliseit/v1/spotify/search?q=${encodeURIComponent(query)}&type=track,artist`, {
                headers: {
                    'X-WP-Nonce': nonce
                }
            });

            if (!res.ok) {
                const errData = await res.json();
                throw new Error(errData.message || 'Search failed');
            }

            const data = await res.json();

            // Normalize results
            let items = [];
            if (data.tracks?.items) {
                items = [...items, ...data.tracks.items.map(t => ({
                    id: t.id,
                    name: t.name,
                    artist: t.artists[0]?.name,
                    image: t.album?.images[2]?.url || t.album?.images[0]?.url,
                    uri: t.uri,
                    type: 'track'
                }))];
            }
            if (data.artists?.items) {
                items = [...items, ...data.artists.items.map(a => ({
                    id: a.id,
                    name: a.name,
                    artist: 'Artist',
                    image: a.images[2]?.url || a.images[0]?.url,
                    uri: a.uri,
                    type: 'artist'
                }))];
            }

            setResults(items);
        } catch (e) {
            console.error(e);
            setError(e.message);
        } finally {
            setIsLoading(false);
        }
    };

    /**
     * Generate code from search result.
     */
    const handleSearchGenerate = async (item) => {
        const restUrl = window.personaliseitData?.restUrl || '/wp-json/';
        const proxyUrl = `${restUrl}personaliseit/v1/spotify/code?uri=${encodeURIComponent(item.uri)}`;
        onSelect({
            uri: item.uri,
            proxyUrl,
            image: item.image,
            name: item.name,
            artist: item.artist
        });
    };

    return (
        <div className="spotify-tool-container" style={{ background: '#f8f9fa', padding: '10px', borderRadius: '4px', border: '1px solid #eee', marginTop: '10px' }}>

            {/* Mode Toggle - only show if search is available */}
            {enableSpotify && (
                <div style={{ display: 'flex', marginBottom: '10px', borderRadius: '4px', overflow: 'hidden', border: '1px solid #ddd' }}>
                    <button
                        type="button"
                        onClick={() => { setMode('paste'); setError(''); }}
                        style={{
                            flex: 1,
                            padding: '6px 10px',
                            border: 'none',
                            background: mode === 'paste' ? '#1DB954' : '#fff',
                            color: mode === 'paste' ? '#fff' : '#333',
                            cursor: 'pointer',
                            fontSize: '12px',
                            fontWeight: mode === 'paste' ? '600' : '400'
                        }}
                    >
                        {__('Paste Link', 'personaliseit')}
                    </button>
                    <button
                        type="button"
                        onClick={() => { setMode('search'); setError(''); }}
                        style={{
                            flex: 1,
                            padding: '6px 10px',
                            border: 'none',
                            borderLeft: '1px solid #ddd',
                            background: mode === 'search' ? '#1DB954' : '#fff',
                            color: mode === 'search' ? '#fff' : '#333',
                            cursor: 'pointer',
                            fontSize: '12px',
                            fontWeight: mode === 'search' ? '600' : '400'
                        }}
                    >
                        {__('Search', 'personaliseit')}
                    </button>
                </div>
            )}

            {/* Paste Mode (Default - No API Required) */}
            {mode === 'paste' && (
                <div>
                    <div className="pi-flex-row" style={{ gap: '5px', marginBottom: '8px' }}>
                        <input
                            type="text"
                            className="pi-modern-input"
                            value={pasteUrl}
                            onChange={(e) => setPasteUrl(e.target.value)}
                            placeholder={__('Paste Spotify URL or URI...', 'personaliseit')}
                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); handlePasteGenerate(); } }}
                            style={{ flex: 1 }}
                        />
                        <button
                            type="button"
                            className="pi-btn primary small"
                            onClick={handlePasteGenerate}
                            disabled={isLoading || !pasteUrl}
                            style={{ background: '#1DB954', borderColor: '#1DB954' }}
                        >
                            {isLoading ? <span className="dashicons dashicons-update spin"></span> : <span className="dashicons dashicons-yes"></span>}
                        </button>
                    </div>
                    <p style={{ fontSize: '11px', color: '#666', margin: '0 0 5px 0' }}>
                        {__('Copy a link from Spotify app or web player', 'personaliseit')}
                    </p>
                    <p style={{ fontSize: '10px', color: '#999', margin: 0 }}>
                        {__('Supports: tracks, albums, artists, playlists', 'personaliseit')}
                    </p>
                </div>
            )}

            {/* Search Mode (Requires API) */}
            {mode === 'search' && (
                <div>
                    <div className="pi-flex-row" style={{ gap: '5px', marginBottom: '10px' }}>
                        <input
                            type="text"
                            className="pi-modern-input"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={__('Search Song or Artist...', 'personaliseit')}
                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); handleSearch(); } }}
                            style={{ flex: 1 }}
                        />
                        <button
                            type="button"
                            className="pi-btn primary small"
                            onClick={handleSearch}
                            disabled={isLoading}
                            style={{ background: '#1DB954', borderColor: '#1DB954' }}
                        >
                            {isLoading ? <span className="dashicons dashicons-update spin"></span> : <span className="dashicons dashicons-search"></span>}
                        </button>
                    </div>

                    <div className="spotify-results" style={{ maxHeight: '200px', overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: '5px' }}>
                        {results.map(item => (
                            <div
                                key={item.id}
                                className="spotify-result-item"
                                onClick={() => handleSearchGenerate(item)}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: '8px',
                                    padding: '5px',
                                    background: '#fff',
                                    border: '1px solid #ddd',
                                    borderRadius: '4px',
                                    cursor: 'pointer',
                                    transition: 'background 0.2s'
                                }}
                                onMouseOver={(e) => e.currentTarget.style.background = '#f0f0f1'}
                                onMouseOut={(e) => e.currentTarget.style.background = '#fff'}
                            >
                                {item.image && <img src={item.image} alt="" style={{ width: '40px', height: '40px', objectFit: 'cover', borderRadius: '2px' }} />}
                                <div style={{ flex: 1, minWidth: 0 }}>
                                    <div style={{ fontWeight: '600', fontSize: '13px', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{item.name}</div>
                                    <div style={{ fontSize: '11px', color: '#666' }}>{item.artist} • {item.type}</div>
                                </div>
                                <span className="dashicons dashicons-arrow-right-alt2" style={{ color: '#ccc' }}></span>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {error && <div style={{ color: '#d63638', fontSize: '12px', marginTop: '8px' }}>{error}</div>}

            <button
                type="button"
                className="pi-btn secondary small"
                onClick={onCancel}
                style={{ width: '100%', marginTop: '10px' }}
            >
                {__('Cancel', 'personaliseit')}
            </button>
        </div>
    );
};

export default SpotifyTool;
