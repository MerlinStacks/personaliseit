/**
 * Clipart search and carousel helpers.
 */

const clipartMethods = {
	filterClipart( layerId ) {
		const grid =
			document.querySelector(
				`.oc-clipart-grid[data-oc-clipart-grid="${ layerId }"]`
			) ||
			document
				.querySelector( `[data-oc-clipart-search="${ layerId }"]` )
				?.closest( '.oc-layer-body' )
				?.querySelector( '.oc-clipart-grid' );
		if ( ! grid ) {
			return;
		}

		const items = grid.querySelectorAll( '.oc-clipart-item' );
		const term = ( this.clipartSearchTerms[ layerId ] || '' )
			.toLowerCase()
			.trim();
		const category = this.clipartCategoryFilters[ layerId ] || '';
		let visibleCount = 0;

		items.forEach( ( btn ) => {
			const name = ( btn.title || '' ).toLowerCase();
			const groups = btn.dataset.ocClipartGroups
				? btn.dataset.ocClipartGroups.split( '||' ).filter( Boolean )
				: [];
			const matchesSearch = ! term || name.includes( term );
			const matchesCategory = ! category || groups.includes( category );
			const visible = matchesSearch && matchesCategory;
			btn.style.display = visible ? '' : 'none';
			if ( visible ) {
				visibleCount++;
			}
		} );

		let noResults = grid.querySelector( '.oc-clipart-no-results' );
		if ( visibleCount === 0 ) {
			if ( ! noResults ) {
				noResults = document.createElement( 'p' );
				noResults.className = 'oc-clipart-no-results';
				noResults.textContent = 'No clipart matches your search.';
				grid.appendChild( noResults );
			}
			noResults.style.display = '';
		} else if ( noResults ) {
			noResults.style.display = 'none';
		}

		this.refreshClipartCarousel( layerId );
	},

	setupClipartCarousels() {
		document
			.querySelectorAll( '[data-oc-clipart-carousel]' )
			.forEach( ( carousel ) => {
				const layerId = parseInt(
					carousel.dataset.ocClipartCarousel,
					10
				);
				const grid = carousel.querySelector(
					'.oc-clipart-grid--carousel'
				);
				if ( ! layerId || ! grid ) {
					return;
				}

				carousel
					.querySelector( '[data-oc-clipart-prev]' )
					?.addEventListener( 'click', () =>
						this.scrollClipartCarousel( layerId, -1 )
					);
				carousel
					.querySelector( '[data-oc-clipart-next]' )
					?.addEventListener( 'click', () =>
						this.scrollClipartCarousel( layerId, 1 )
					);
				grid.addEventListener(
					'scroll',
					() => this.updateClipartCarouselDots( layerId ),
					{ passive: true }
				);
				this.refreshClipartCarousel( layerId );
			} );
	},

	visibleClipartItems( grid ) {
		return Array.from( grid.querySelectorAll( '.oc-clipart-item' ) ).filter(
			( item ) => item.style.display !== 'none'
		);
	},

	clipartCarouselPageCount( grid ) {
		const visibleItems = this.visibleClipartItems( grid );
		if ( ! visibleItems.length || ! grid.clientWidth ) {
			return 1;
		}
		return Math.max( 1, Math.ceil( grid.scrollWidth / grid.clientWidth ) );
	},

	scrollClipartCarousel( layerId, direction ) {
		const grid = document.querySelector(
			`.oc-clipart-grid--carousel[data-oc-clipart-grid="${ layerId }"]`
		);
		if ( ! grid ) {
			return;
		}
		const page =
			Math.round( grid.scrollLeft / Math.max( 1, grid.clientWidth ) ) +
			direction;
		const maxPage = this.clipartCarouselPageCount( grid ) - 1;
		grid.scrollTo( {
			left: Math.max( 0, Math.min( maxPage, page ) ) * grid.clientWidth,
			behavior: 'smooth',
		} );
	},

	refreshClipartCarousel( layerId ) {
		const carousel = document.querySelector(
			`[data-oc-clipart-carousel="${ layerId }"]`
		);
		const grid = carousel?.querySelector( '.oc-clipart-grid--carousel' );
		const dots = carousel?.querySelector( '[data-oc-clipart-dots]' );
		if ( ! carousel || ! grid || ! dots ) {
			return;
		}

		const pageCount = this.clipartCarouselPageCount( grid );
		const maxLeft = Math.max( 0, ( pageCount - 1 ) * grid.clientWidth );
		if ( grid.scrollLeft > maxLeft ) {
			grid.scrollLeft = maxLeft;
		}
		dots.innerHTML = '';
		for ( let i = 0; i < pageCount; i++ ) {
			const dot = document.createElement( 'button' );
			dot.type = 'button';
			dot.className = 'oc-clipart-carousel-dot';
			dot.setAttribute( 'aria-label', `Go to clipart page ${ i + 1 }` );
			dot.addEventListener( 'click', () =>
				grid.scrollTo( {
					left: i * grid.clientWidth,
					behavior: 'smooth',
				} )
			);
			dots.appendChild( dot );
		}

		carousel.classList.toggle(
			'oc-clipart-carousel--single-page',
			pageCount <= 1
		);
		this.updateClipartCarouselDots( layerId );
	},

	updateClipartCarouselDots( layerId ) {
		const carousel = document.querySelector(
			`[data-oc-clipart-carousel="${ layerId }"]`
		);
		const grid = carousel?.querySelector( '.oc-clipart-grid--carousel' );
		if ( ! carousel || ! grid ) {
			return;
		}

		const pageCount = this.clipartCarouselPageCount( grid );
		const page = Math.max(
			0,
			Math.min(
				pageCount - 1,
				Math.round( grid.scrollLeft / Math.max( 1, grid.clientWidth ) )
			)
		);
		carousel
			.querySelectorAll( '.oc-clipart-carousel-dot' )
			.forEach( ( dot, i ) => {
				dot.classList.toggle( 'oc-active', i === page );
				dot.setAttribute(
					'aria-current',
					i === page ? 'true' : 'false'
				);
			} );
		carousel
			.querySelector( '[data-oc-clipart-prev]' )
			?.toggleAttribute( 'disabled', page <= 0 );
		carousel
			.querySelector( '[data-oc-clipart-next]' )
			?.toggleAttribute( 'disabled', page >= pageCount - 1 );
	},
};

export default clipartMethods;
