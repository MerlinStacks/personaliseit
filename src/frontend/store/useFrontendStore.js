import { create } from 'zustand';
import { createHistorySlice } from '../../common/store/historySlice';
import { createViewSlice } from '../../common/store/viewSlice';
import { migrateData } from '../../common/utils/DataMigrator';

const useFrontendStore = create((set, get) => ({
	config: null,
	views: [],
	currentViewId: null,
	variations: [],
	currentVariationId: null,
	productImage: '',
	fonts: [],
	userInputs: {}, // Map of layerId -> text value
	userStyles: {}, // Map of layerId -> { fontFamily, color }
	activePalette: null,
	setActivePalette: (palette) => set({ activePalette: palette }),

	// Global Embroidery Color State
	embroideryColor: null, // { name: 'Black', code: '#000000' }
	setEmbroideryColor: (colorObj) => set({ embroideryColor: colorObj }),

	// A11y
	a11yMessage: '',
	setA11yMessage: (msg) => set({ a11yMessage: msg }),

	stageRef: null,
	setStageRef: (ref) => set({ stageRef: ref }),

	setConfig: (config) => {
		let views = [];
		let variations = config.variations || [];

		if (config.views && config.views.length > 0) {
			views = config.views;
		} else if (config.layers) {
			views = [
				{
					id: 'front',
					name: 'Front',
					image: get().productImage,
					overlayImage: config.overlayImage || null,
					layers: config.layers || [],
				},
			];
		} else {
			views = [
				{
					id: 'front',
					name: 'Front',
					image: get().productImage,
					overlayImage: null,
					layers: [],
				},
			];
		}

		set({
			config,
			views,
			currentViewId: views[0]?.id || null,
			variations,
			currentVariationId:
				variations.length > 0 ? variations[0].id : null,
		});


		// Hydrate from Shared Design if present
		if (typeof window !== 'undefined' && window.personaliseitData?.sharedDesign) {
			const shared = window.personaliseitData.sharedDesign;
			// MIGRATE DATA
			const clean = migrateData(shared);

			// Restore Views (Layers positions/transforms)
			if (clean.views) set({ views: clean.views });

			set({
				userInputs: clean.userInputs,
				userStyles: clean.userStyles,
				embroideryColor: clean.embroideryColor || null
			});
		}

		set({ a11yMessage: 'Personalization configuration loaded.' });
	},

	setCurrentViewId: (id) => {
		set({ currentViewId: id });
		const viewName = get().views.find(v => v.id === id)?.name || 'View';
		set({ a11yMessage: `Switched to ${viewName} view.` });
	},
	setCurrentVariationId: (id) => set({ currentVariationId: id }),
	setProductImage: (url) => set({ productImage: url }),
	setFonts: (fonts) => set({ fonts }),
	setUserInputs: (inputs) => set({ userInputs: inputs }),
	setUserStyles: (styles) => set({ userStyles: styles }),


	// --- SHARED HISTORY ---
	...createHistorySlice(set, get, { keys: ['views', 'userInputs', 'userStyles', 'embroideryColor'] }),

	// Draft Actions
	saveDraft: () => {
		const state = get();
		const productId = window.personaliseitData?.productId || 'default';
		const draft = {
			userInputs: state.userInputs,
			userStyles: state.userStyles,
			views: state.views,
			currentViewId: state.currentViewId,
			embroideryColor: state.embroideryColor,
			timestamp: Date.now()
		};
		try {
			localStorage.setItem(`personaliseit_draft_${productId}`, JSON.stringify(draft));
			set({ a11yMessage: 'Draft saved successfully.' });
			return true;
		} catch (e) {
			console.error('Failed to save draft', e);
			return false;
		}
	},


	loadDraft: () => {
		const productId = window.personaliseitData?.productId || 'default';
		const raw = localStorage.getItem(`personaliseit_draft_${productId}`);
		if (!raw) return false;
		try {
			const draft = JSON.parse(raw);
			const clean = migrateData(draft);

			set({
				userInputs: clean.userInputs,
				userStyles: clean.userStyles,
				views: clean.views || [],
				currentViewId: clean.currentViewId || null,
				embroideryColor: clean.embroideryColor || null,
				past: [],
				future: [],
				a11yMessage: 'Draft loaded successfully.'
			});
			return true;
		} catch (e) {
			console.error('Failed to load draft', e);
			return false;
		}
	},

	checkDraftExists: () => {
		const productId = window.personaliseitData?.productId || 'default';
		return !!localStorage.getItem(`personaliseit_draft_${productId}`);
	},

	lastInputTime: 0,

	updateInput: (layerId, value) =>
		set((state) => {
			const now = Date.now();
			let newPast = state.past;

			// Debounce History: Only snapshot if > 1s has passed since last input 
			// OR if the layerId changed (focus change)? No, straightforward time check is safest for now.
			if (now - state.lastInputTime > 1000) {
				const currentSnapshot = {
					userInputs: JSON.parse(JSON.stringify(state.userInputs)),
					userStyles: JSON.parse(JSON.stringify(state.userStyles)),
					views: JSON.parse(JSON.stringify(state.views)),
					embroideryColor: state.embroideryColor
				};
				newPast = [...state.past, currentSnapshot].slice(-20);
			}

			return {
				lastInputTime: now,
				past: newPast,
				future: [],
				userInputs: {
					...state.userInputs,
					[layerId]: value,
				},
			};
		}),

	updateStyle: (layerId, style) =>
		set((state) => {
			const now = Date.now();
			let newPast = state.past;

			if (now - state.lastInputTime > 1000) {
				const currentSnapshot = {
					userInputs: JSON.parse(JSON.stringify(state.userInputs)),
					userStyles: JSON.parse(JSON.stringify(state.userStyles)),
					views: JSON.parse(JSON.stringify(state.views)),
					embroideryColor: state.embroideryColor
				};
				newPast = [...state.past, currentSnapshot].slice(-20);
			}

			return {
				lastInputTime: now,
				past: newPast,
				future: [],
				userStyles: {
					...state.userStyles,
					[layerId]: {
						...(state.userStyles[layerId] || {}),
						...style,
					},
				},
			};
		}),


	// --- SHARED LAYER ACTIONS (Partially used, also has custom inputs logic) ---
	// Frontend uses addLayer but also updateInput/updateStyle.
	// We can use the viewSlice for addLayer, but we need to ensure it triggers history properly.
	// viewSlice calls saveHistory() internally.
	...createViewSlice(set, get),

	/**
	 * Validate all text layers for min/max length requirements.
	 * Returns { isValid: boolean, errors: [{ layerId, message }] }
	 */
	getValidationErrors: () => {
		const state = get();
		const errors = [];

		state.views.forEach(view => {
			(view.layers || []).forEach(layer => {
				if (layer.type === 'text') {
					const value = state.userInputs[layer.id] || '';
					const minLength = layer.minLength || 0;
					const maxLength = layer.maxLength || 999;

					// Check required
					if (layer.required && value.length === 0) {
						errors.push({
							layerId: layer.id,
							layerLabel: layer.label || 'Text',
							message: `"${layer.label || 'Text'}" is required`
						});
					}
					// Check minLength
					else if (minLength > 0 && value.length > 0 && value.length < minLength) {
						errors.push({
							layerId: layer.id,
							layerLabel: layer.label || 'Text',
							message: `"${layer.label || 'Text'}" requires at least ${minLength} characters`
						});
					}
				}
			});
		});

		return {
			isValid: errors.length === 0,
			errors
		};
	},
}));

export default useFrontendStore;
