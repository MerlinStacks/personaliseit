/* eslint-disable no-alert, no-undef */

export function createMockupPicker( deps ) {
	const { commitChange, getSelectedIndex, selectedArea } = deps;
	let mediaFrame = null;

	function openMockupPicker() {
		if ( getSelectedIndex() < 0 ) {
			return;
		}
		if ( ! window.wp || ! window.wp.media ) {
			alert( 'Media library is not available.' );
			return;
		}
		if ( ! mediaFrame ) {
			const data = window.ocProductsData || {};
			mediaFrame = wp.media( {
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
				const area = selectedArea();
				if ( area ) {
					area.mockupId = att.id;
					area.mockupUrl =
						( att.sizes &&
							att.sizes.large &&
							att.sizes.large.url ) ||
						att.url;
					commitChange( { all: true } );
				}
			} );
		}
		mediaFrame.open();
	}

	return openMockupPicker;
}
