import { readFile } from 'node:fs/promises';

const source = (
	await readFile( 'src/shared/preview-font.js', 'utf8' )
).replace(
	/import fallbackUrl from '[^']+';/,
	"const fallbackUrl = 'fallback.ttf';"
);
export const previewFonts = await import(
	`data:text/javascript;base64,${ Buffer.from( source ).toString(
		'base64'
	) }`
);
