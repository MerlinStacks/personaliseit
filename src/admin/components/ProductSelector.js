import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { SearchControl, Spinner, Card, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import useStore from '../store/useStore';

const ProductSelector = () => {
	const [ searchTerm, setSearchTerm ] = useState( '' );
	const [ products, setProducts ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const setSelectedProduct = useStore(
		( state ) => state.setSelectedProduct
	);

	useEffect( () => {
		const delayDebounceFn = setTimeout( () => {
			fetchProducts( searchTerm );
		}, 500 );

		return () => clearTimeout( delayDebounceFn );
	}, [ searchTerm ] );

	const fetchProducts = async ( search ) => {
		setIsLoading( true );
		try {
			const results = await apiFetch( {
				path: `/personaliseit/v1/products?search=${ search }`,
			} );
			setProducts( results );
		} catch ( error ) {
			console.error( error );
		} finally {
			setIsLoading( false );
		}
	};

	return (
		<div className="product-selector">
			<div className="product-selector-header">
				<div>
					<h2>
						{ __(
							'Select a Product to Personalize',
							'personaliseit'
						) }
					</h2>
					<p className="description">
						{ __(
							'Choose a product to configure personalization options',
							'personaliseit'
						) }
					</p>
				</div>
			</div>

			<div className="product-selector-search">
				<SearchControl
					label={ __( 'Search Products', 'personaliseit' ) }
					value={ searchTerm }
					onChange={ setSearchTerm }
					placeholder={ __(
						'Search by product name...',
						'personaliseit'
					) }
				/>
			</div>

			{ isLoading && (
				<div className="product-selector-loading">
					<Spinner />
					<p>{ __( 'Loading products...', 'personaliseit' ) }</p>
				</div>
			) }

			{ ! isLoading && products.length === 0 && (
				<div className="product-selector-empty">
					<div className="empty-state">
						<span
							className="dashicons dashicons-products"
							style={ { fontSize: '48px', opacity: 0.3 } }
						></span>
						<h3>{ __( 'No products found', 'personaliseit' ) }</h3>
						<p>
							{ __(
								'Try adjusting your search terms or check that you have WooCommerce products.',
								'personaliseit'
							) }
						</p>
					</div>
				</div>
			) }

			{ ! isLoading && products.length > 0 && (
				<div className="product-grid">
					{ products.map( ( product ) => (
						<Card
							key={ product.id }
							className="product-card"
							onClick={ () => setSelectedProduct( product ) }
						>
							<CardBody>
								<div className="product-card-image">
									{ product.image ? (
										<img
											src={ product.image }
											alt={ product.title }
										/>
									) : (
										<div className="product-card-placeholder">
											<span className="dashicons dashicons-format-image"></span>
										</div>
									) }
								</div>
								<div className="product-card-content">
									<h3 className="product-title">
										{ product.title }
									</h3>
									{ product.price && (
										<p className="product-price">
											{ product.price }
										</p>
									) }
									<button className="product-select-button">
										<span className="dashicons dashicons-admin-customizer"></span>
										{ __( 'Configure', 'personaliseit' ) }
									</button>
								</div>
							</CardBody>
						</Card>
					) ) }
				</div>
			) }
		</div>
	);
};

export default ProductSelector;
