( function () {
	const form = document.getElementById( 'oc-customer-upload-bulk-form' );
	if ( ! form ) {
		return;
	}

	const selectAll = form.querySelector( '[data-oc-upload-select-all]' );
	const deleteButton = form.querySelector( '[data-oc-upload-bulk-delete]' );
	const uploadCheckboxes = Array.from(
		document.querySelectorAll( '[data-oc-upload-select]' )
	);

	const updateControls = () => {
		const selectedCount = uploadCheckboxes.filter(
			( checkbox ) => checkbox.checked
		).length;

		selectAll.checked = selectedCount === uploadCheckboxes.length;
		selectAll.indeterminate = selectedCount > 0 && selectedCount < uploadCheckboxes.length;
		deleteButton.disabled = selectedCount === 0;
		deleteButton.textContent = selectedCount
			? `${ deleteButton.dataset.label } (${ selectedCount })`
			: deleteButton.dataset.label;
	};

	selectAll.addEventListener( 'change', () => {
		uploadCheckboxes.forEach( ( checkbox ) => {
			checkbox.checked = selectAll.checked;
		} );
		updateControls();
	} );

	uploadCheckboxes.forEach( ( checkbox ) => {
		checkbox.addEventListener( 'change', updateControls );
	} );

	form.addEventListener( 'submit', async ( event ) => {
		const selected = uploadCheckboxes.filter(
			( checkbox ) => checkbox.checked
		);
		const selectedCount = selected.length;
		if (
			selectedCount === 0 ||
			! window.confirm(
				selectedCount === 1
					? deleteButton.dataset.confirmSingular
					: deleteButton.dataset.confirmPlural.replace(
						'%s',
						selectedCount
					)
			)
		) {
			event.preventDefault();
			return;
		}

		if ( ! form.dataset.ajaxUrl || ! window.fetch ) {
			return;
		}

		event.preventDefault();
		deleteButton.disabled = true;
		selectAll.disabled = true;
		uploadCheckboxes.forEach( ( checkbox ) => {
			checkbox.disabled = true;
		} );

		const nonce = form.querySelector( '[name="_wpnonce"]' ).value;
		let deleted = 0;
		let skipped = 0;

		// Keep each expensive reference check in its own bounded request.
		for ( let index = 0; index < selectedCount; index += 1 ) {
			deleteButton.textContent = deleteButton.dataset.progress
				.replace( '%1$s', index + 1 )
				.replace( '%2$s', selectedCount );

			try {
				// eslint-disable-next-line no-await-in-loop
				const response = await window.fetch( form.dataset.ajaxUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: new URLSearchParams( {
						action: 'oc_delete_customer_upload',
						_wpnonce: nonce,
						id: selected[ index ].value,
					} ),
				} );
				const result = await response.json();
				if ( response.ok && result.success ) {
					deleted += 1;
				} else {
					skipped += 1;
				}
			} catch {
				skipped += 1;
			}
		}

		const redirect = new URL( window.location.href );
		redirect.searchParams.delete( 'paged' );
		redirect.searchParams.set( 'bulk_deleted', deleted );
		redirect.searchParams.set( 'bulk_skipped', skipped );
		window.location.assign( redirect.toString() );
	} );
} )();
