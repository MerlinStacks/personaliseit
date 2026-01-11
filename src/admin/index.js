import { createRoot, useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ProductSelector from './components/ProductSelector';
import Designer from './components/Designer';
import FontManager from './components/FontManager';
import PaletteManager from './components/PaletteManager';
import AssetManager from './components/AssetManager';
import StyleManager from './components/StyleManager';
import Settings from './components/Settings';
import ExportRenderer from './components/ExportRenderer';
import TemplateManager from './components/TemplateManager';
import useStore from './store/useStore';
import './style.scss';

const App = ({ initialPage }) => {
	const selectedProduct = useStore((state) => state.selectedProduct);
	const orderMode = useStore((state) => state.orderMode);
	const setOrderMode = useStore((state) => state.setOrderMode);
	const setTemplateId = useStore((state) => state.setTemplateId);
	const [currentPage, setCurrentPage] = useState(initialPage || 'designer');

	const fetchSettings = useStore((state) => state.fetchSettings);

	useEffect(() => {
		fetchSettings();

		// Check for Order Mode parameters
		const params = new URLSearchParams(window.location.search);
		const orderId = params.get('order_id');
		const itemId = params.get('item_id');
		if (orderId && itemId && initialPage === 'designer') {
			setOrderMode({ orderId, itemId });
		}

		const templateId = params.get('template_id');
		if (templateId && initialPage === 'designer') {
			setTemplateId(templateId);
		}
	}, []);

	// If on fonts page, always show FontManager
	if (currentPage === 'fonts') {
		return (
			<div className="personaliseit-admin-app">
				<header className="app-header">
					<h1>{__('Font Library', 'personaliseit')}</h1>
				</header>
				<main>
					<FontManager />
				</main>
			</div>
		);
	}

	// If on assets page
	if (currentPage === 'assets') {
		return (
			<div className="personaliseit-admin-app">
				<main style={{ height: 'calc(100vh - 50px)', overflow: 'hidden' }}>
					<AssetManager />
				</main>
			</div>
		);
	}

	if (currentPage === 'settings') {
		return (
			<div className="personaliseit-admin-app">
				<main>
					<Settings />
				</main>
			</div>
		);
	}

	if (currentPage === 'colors') {
		return (
			<div className="personaliseit-admin-app">
				<header className="app-header">
					<h1>{__('Color Palettes', 'personaliseit')}</h1>
				</header>
				<main>
					<PaletteManager />
				</main>
			</div>
		);
	}

	if (currentPage === 'styles') {
		return (
			<div className="personaliseit-admin-app">
				<header className="app-header">
					<h1>{__('Artist Styles', 'personaliseit')}</h1>
				</header>
				<main>
					<StyleManager />
				</main>
			</div>
		);
	}

	if (currentPage === 'export') {
		return <ExportRenderer />;
	}

	if (currentPage === 'templates') {
		return (
			<div className="personaliseit-admin-app">
				<main>
					<TemplateManager />
				</main>
			</div>
		);
	}


	// Designer page
	return (
		<div className="personaliseit-admin-app">
			<header className="app-header">
				<h1>{__('Personalise It! Designer', 'personaliseit')}</h1>
			</header>
			<main>
				{!selectedProduct && !orderMode ? <ProductSelector /> : <Designer />}
			</main>
		</div>
	);
};

document.addEventListener('DOMContentLoaded', () => {
	const rootElement = document.getElementById('personaliseit-admin-root');
	if (rootElement) {
		const page = rootElement.getAttribute('data-page') || 'designer';
		createRoot(rootElement).render(<App initialPage={page} />);
	}
});
