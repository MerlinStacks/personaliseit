import { create } from 'zustand';
import apiFetch from '@wordpress/api-fetch';
import { createHistorySlice } from '../../common/store/historySlice';
import { createViewSlice } from '../../common/store/viewSlice';

const useStore = create((set, get) => ({
	selectedProduct: null,
	personalisationMethod: 'none', // 'engraving', 'embroidery', etc.
	setPersonalisationMethod: (method) => set({ personalisationMethod: method }),

	paletteMap: {}, // { 'engraving': 'palette_id' }
	setPaletteMap: (map) => set({ paletteMap: map }),
	setPaletteForMethod: (method, paletteId) => set((state) => ({ paletteMap: { ...state.paletteMap, [method]: paletteId } })),

	views: [],
	currentViewId: null,
	variations: [], // [{ id: 'var_1', name: 'Red' }, { id: 'var_2', name: 'Blue' }]
	currentVariationId: null, // For editing context
	selectedLayerId: null,
	selectedLayerIds: [], // Multi-select support

	setSelectedLayerId: (id) =>
		set((state) => {
			if (id === null) return { selectedLayerId: null, selectedLayerIds: [] };
			// Single selection clears others
			return { selectedLayerId: id, selectedLayerIds: [id] };
		}),

	toggleLayerSelection: (id) =>
		set((state) => {
			const current = state.selectedLayerIds || [];
			const exists = current.includes(id);
			let newIds;
			if (exists) {
				newIds = current.filter((i) => i !== id);
			} else {
				newIds = [...current, id];
			}
			// Update single ID to the last one selected, or null
			const singleId = newIds.length > 0 ? newIds[newIds.length - 1] : null;
			return { selectedLayerIds: newIds, selectedLayerId: singleId };
		}),

	clearSelection: () => set({ selectedLayerId: null, selectedLayerIds: [] }),

	orderMode: null, // { orderId, itemId }
	orderMode: null, // { orderId, itemId }
	setOrderMode: (data) => set({ orderMode: data }),

	templateId: null, // For editing design templates
	setTemplateId: (id) => set({ templateId: id }),

	setSelectedProduct: (product) => set({ selectedProduct: product }),

	settings: {
		canvasWidth: 800,
		canvasHeight: 800,
		maxUploadSize: 5,
		enabledMethods: {
			engraving: true,
			embroidery: true,
			dtf: true,
			uv: true,
			sublimation: true
		}
	},
	fetchSettings: async () => {
		try {
			const data = await apiFetch({ path: '/wp/v2/settings' });
			console.log('PersonaliseIt Settings Raw:', data);
			const isEnabled = (val) => val === true || val === '1' || val === 1 || val === 'true';

			set({
				settings: {
					canvasWidth:
						parseInt(data.personaliseit_canvas_width) || 800,
					canvasHeight:
						parseInt(data.personaliseit_canvas_height) || 800,
					maxUploadSize:
						parseInt(data.personaliseit_max_upload_size) || 5,
					enabledMethods: {
						engraving: isEnabled(data.personaliseit_enable_engraving),
						embroidery: isEnabled(data.personaliseit_enable_embroidery),
						dtf: isEnabled(data.personaliseit_enable_dtf),
						uv: isEnabled(data.personaliseit_enable_uv),
						sublimation: isEnabled(data.personaliseit_enable_sublimation),
					}
				},
			});
		} catch (e) {
			console.error('Error fetching settings', e);
		}
	},

	// Configuration Management
	exportConfig: () => {
		const state = get();
		return {
			version: '1.0.0',
		};
	},
	importConfig: (config) =>
		set((state) => {
			// Basic validation could go here
			return {
				personalisationMethod: config.personalisationMethod || 'none',
				paletteMap: config.paletteMap || {},
				views: config.views || [],
				variations: config.variations || [],
				settings: config.settings || {},
				currentViewId:
					config.views && config.views.length > 0
						? config.views[0].id
						: null,
			};
		}),

	// --- SHARED HISTORY ---
	...createHistorySlice(set, get, { keys: ['views'] }),

	// View Actions
	setViews: (views) => set({ views }),
	setCurrentViewId: (id) => set({ currentViewId: id }),

	addView: (view) =>
		set((state) => {
			const viewsClone = JSON.parse(JSON.stringify(state.views));
			return {
				past: [...state.past, viewsClone].slice(-20),
				future: [],
				views: [...state.views, view],
				currentViewId: view.id,
			};
		}),

	updateView: (id, newAttrs) =>
		set((state) => {
			const viewsClone = JSON.parse(JSON.stringify(state.views));
			return {
				past: [...state.past, viewsClone].slice(-20),
				future: [],
				views: state.views.map((view) =>
					view.id === id ? { ...view, ...newAttrs } : view
				),
			};
		}),

	removeView: (id) =>
		set((state) => {
			const viewsClone = JSON.parse(JSON.stringify(state.views));
			const newViews = state.views.filter((v) => v.id !== id);
			let newCurrentId = state.currentViewId;
			if (id === state.currentViewId) {
				newCurrentId = newViews.length > 0 ? newViews[0].id : null;
			}
			return {
				past: [...state.past, viewsClone].slice(-20),
				future: [],
				views: newViews,
				currentViewId: newCurrentId,
			};
		}),

	// Variation Actions
	setVariations: (variations) => set({ variations }),
	setCurrentVariationId: (id) => set({ currentVariationId: id }),

	addVariation: (variation) =>
		set((state) => ({
			variations: [...state.variations, variation],
			currentVariationId: variation.id,
		})),

	removeVariation: (id) =>
		set((state) => ({
			variations: state.variations.filter((v) => v.id !== id),
			currentVariationId:
				state.currentVariationId === id
					? state.variations[0]?.id || null
					: state.currentVariationId,
		})),


	// --- SHARED LAYER ACTIONS ---
	...createViewSlice(set, get),

	// Reorder layers (for drag and drop)
	reorderLayers: (startIndex, endIndex) =>
		set((state) => {
			const viewsClone = JSON.parse(JSON.stringify(state.views));
			return {
				past: [...state.past, viewsClone].slice(-20),
				future: [],
				views: state.views.map((v) => {
					if (v.id === state.currentViewId) {
						const newLayers = Array.from(v.layers);
						const [removed] = newLayers.splice(startIndex, 1);
						newLayers.splice(endIndex, 0, removed);
						return { ...v, layers: newLayers };
					}
					return v;
				}),
			};
		}),

	// Duplicate layer
	duplicateLayer: (id) =>
		set((state) => {
			const viewsClone = JSON.parse(JSON.stringify(state.views));
			return {
				past: [...state.past, viewsClone].slice(-20),
				future: [],
				views: state.views.map((v) => {
					if (v.id === state.currentViewId) {
						const layerToDuplicate = v.layers.find(
							(l) => l.id === id
						);
						if (layerToDuplicate) {
							const newLayer = {
								...layerToDuplicate,
								id: `layer_${Date.now()}`,
								label: `${layerToDuplicate.label} Copy`,
								x: layerToDuplicate.x + 20,
								y: layerToDuplicate.y + 20,
							};
							return { ...v, layers: [...v.layers, newLayer] };
						}
					}
					return v;
				}),
			};
		}),

	// Flip layer (for images)
	flipLayer: (id, direction) =>
		set((state) => {
			const viewsClone = JSON.parse(JSON.stringify(state.views));
			return {
				past: [...state.past, viewsClone].slice(-20),
				future: [],
				views: state.views.map((v) =>
					v.id === state.currentViewId
						? {
							...v,
							layers: v.layers.map((l) => {
								if (l.id === id) {
									if (direction === 'horizontal') {
										return {
											...l,
											scaleX: (l.scaleX || 1) * -1,
										};
									} else if (direction === 'vertical') {
										return {
											...l,
											scaleY: (l.scaleY || 1) * -1,
										};
									}
								}
								return l;
							}),
						}
						: v
				),
			};
		}),
}));

export default useStore;
