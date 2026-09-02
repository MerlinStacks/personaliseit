import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

test( 'AI image requests and cart payloads never send private admin instructions', async () => {
	const [ uploadSource, serialisationSource, frontendSource ] =
		await Promise.all( [
			readFile( 'src/frontend/customiser/uploads.js', 'utf8' ),
			readFile( 'src/frontend/customiser/cart-serialization.js', 'utf8' ),
			readFile( 'includes/frontend/class-oc-frontend.php', 'utf8' ),
		] );

	assert.doesNotMatch( uploadSource, /ai_prompt_instruction/ );
	assert.match( serialisationSource, /delete input\.aiDescription/ );
	assert.match( frontendSource, /generateAiImageUrl/ );
	assert.match(
		frontendSource,
		/unset\( \$settings\['ai_prompt_instruction'\] \)/
	);
} );
