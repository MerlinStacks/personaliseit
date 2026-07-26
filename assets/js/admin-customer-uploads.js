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

	form.addEventListener( 'submit', ( event ) => {
		const selectedCount = uploadCheckboxes.filter(
			( checkbox ) => checkbox.checked
		).length;
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
		}
	} );
} )();
