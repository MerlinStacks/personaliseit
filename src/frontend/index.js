
import { createRoot } from '@wordpress/element';
import useFrontendStore from './store/useFrontendStore';
import CanvasComponent from './components/CanvasComponent';
import ControlsComponent from './components/ControlsComponent';
import CanvasVisibilityManager from './components/CanvasVisibilityManager';
import FontService from '../common/services/FontService';
import './style.scss';

document.addEventListener('DOMContentLoaded', async () => {
	const canvasContainer = document.getElementById(
		'personaliseit-canvas-container'
	);
	const controlsContainer = document.getElementById(
		'personaliseit-controls-container'
	);



	if (!canvasContainer && !controlsContainer) return;

	// Initialize data once
	const setConfig = useFrontendStore.getState().setConfig;
	const setProductImage = useFrontendStore.getState().setProductImage;
	const setFonts = useFrontendStore.getState().setFonts;
	const setActivePalette = useFrontendStore.getState().setActivePalette;

	if (window.personaliseitData) {

		setConfig(window.personaliseitData.config);
		setProductImage(window.personaliseitData.productImage);
		if (window.personaliseitData.activePalette) {
			setActivePalette(window.personaliseitData.activePalette);
		}
	} else {
		console.error('window.personaliseitData is missing!');
	}

	// Fetch Fonts
	fetch((window.personaliseitData?.restUrl || '/wp-json/') + 'personaliseit/v1/fonts')
		.then((res) => res.json())
		.then((data) => {
			if (Array.isArray(data)) {
				setFonts(data);
				FontService.loadFontsIntoDom(data);
			}
		})
		.catch((error) => console.error('Error fetching fonts:', error));


	// Render components
	let canvasRootContainer = canvasContainer;

	// Self-Healing: If canvas container is missing (hook stripped by theme) but permissions exist (controls visible),
	// create the container manually so the app can start.
	if (!canvasRootContainer && controlsContainer) {
		console.warn("PersonaliseIt: Canvas container missing provided by PHP hook. Creating manually.");
		canvasRootContainer = document.createElement('div');
		canvasRootContainer.id = 'personaliseit-canvas-container';
		// Initial style to avoid FOUC, layout manager will handle positioning
		canvasRootContainer.style.display = 'none';
		document.body.appendChild(canvasRootContainer);
	}

	// PORTAL OVERLAY: Create a distinct container for the actual canvas visual
	const portalOverlayId = 'personaliseit-portal-overlay';
	let portalOverlay = document.getElementById(portalOverlayId);
	if (!portalOverlay) {
		portalOverlay = document.createElement('div');
		portalOverlay.id = portalOverlayId;
		// Ensure it lives at the body level for correct absolute positioning
		document.body.appendChild(portalOverlay);
	}

	if (canvasRootContainer) {
		createRoot(canvasRootContainer).render(
			<>
				<CanvasComponent />
				<CanvasVisibilityManager />
			</>
		);
	}

	if (controlsContainer) {
		createRoot(controlsContainer).render(<ControlsComponent />);
	}
});
