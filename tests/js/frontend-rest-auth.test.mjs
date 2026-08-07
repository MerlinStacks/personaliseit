import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

test( 'does not expose or send a WordPress REST nonce for guests', async () => {
	const [ phpSource, jsSource ] = await Promise.all( [
		readFile( 'includes/frontend/class-oc-frontend.php', 'utf8' ),
		readFile( 'src/frontend/customiser-app.js', 'utf8' ),
	] );

	assert.match(
		phpSource,
		/\['uploadNonce'\]\s*=\s*is_user_logged_in\(\)\s*\?\s*wp_create_nonce\( 'wp_rest' \)\s*:\s*'';/
	);
	assert.match(
		jsSource,
		/if \( this\.data\.uploadNonce \) \{\s*headers\[ 'X-WP-Nonce' \] = this\.data\.uploadNonce;/
	);
	assert.match(
		jsSource,
		/if \( this\.data\.requestToken \) \{\s*headers\[ 'X-OC-Token' \] = this\.data\.requestToken;/
	);
} );
