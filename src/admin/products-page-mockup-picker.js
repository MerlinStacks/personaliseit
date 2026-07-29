/* eslint-disable no-alert */

export function createMockupPicker( deps ) {
	const { getAreas, getSelectedIndex, renderAll, snapshot } = deps;
	let mediaFrame = null;

	function openMockupPicker() {
		if ( getSelectedIndex() < 0 ) {
			return;
		}
		if ( ! window.wp || ! window.wp.media ) {
			window.alert( 'Media library is not available.' );
			return;
		}
		if ( ! mediaFrame ) {
			const data = window.ocProductsData || {};
			mediaFrame = window.wp.media( {
				title: data.mediaTitle || 'Select Mockup Image',
				button: { text: data.mediaBtn || 'Use as Mockup' },
				library: { type: 'image' },
				multiple: false,
			} );
			mediaFrame.on( 'select', () => {
				const att = mediaFrame
					.state()
					.get( 'selection' )
					.first()
					.toJSON();
				const mockupUrl =
					( att.sizes && att.sizes.large && att.sizes.large.url ) ||
					att.url;
				getAreas().forEach( ( area ) => {
					area.mockupId = Number( att.id ) || 0;
					area.mockupUrl = mockupUrl || '';
					area.storedMockupId = Number( att.id ) || 0;
				} );
				snapshot();
				renderAll();
			} );
		}
		mediaFrame.open();
	}

	return openMockupPicker;
}
