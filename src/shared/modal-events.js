export function shouldOpenPreviewModal( event ) {
	return Boolean(
		! event.defaultPrevented &&
			event.button === 0 &&
			! event.metaKey &&
			! event.ctrlKey &&
			! event.shiftKey &&
			! event.altKey
	);
}
