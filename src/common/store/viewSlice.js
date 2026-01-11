// Helper to find and update a layer within a list of views
export const updateLayerInViews = (views, viewId, layerId, updateFn) => {
    return views.map(view => {
        if (view.id === viewId) {
            return {
                ...view,
                layers: view.layers.map(layer =>
                    layer.id === layerId ? (typeof updateFn === 'function' ? updateFn(layer) : { ...layer, ...updateFn }) : layer
                )
            };
        }
        return view;
    });
};

// Helper to add a layer to a specific view
export const addLayerToView = (views, viewId, layer) => {
    return views.map(view => {
        if (view.id === viewId) {
            return {
                ...view,
                layers: [...view.layers, layer]
            };
        }
        return view;
    });
};

// Helper to remove a layer from a specific view
export const removeLayerFromView = (views, viewId, layerId) => {
    return views.map(view => {
        if (view.id === viewId) {
            return {
                ...view,
                layers: view.layers.filter(l => l.id !== layerId)
            };
        }
        return view;
    });
};

export const createViewSlice = (set, get) => ({
    // This slice assumes 'views' and 'currentViewId' exist in the store
    // It provides ACTIONS that use the helpers above + saveHistory

    addLayer: (layer) => {
        const { saveHistory, views, currentViewId } = get();
        saveHistory();
        set({ views: addLayerToView(views, currentViewId, layer) });
    },

    updateLayer: (id, newAttrs) => {
        const { saveHistory, views, currentViewId } = get();
        saveHistory();
        set({ views: updateLayerInViews(views, currentViewId, id, newAttrs) });
    },

    removeLayer: (id) => {
        const { saveHistory, views, currentViewId, selectedLayerId } = get();
        // saveHistory(); // Maybe don't save history for selection changes? 
        // Logic: Removal IS a history event.
        saveHistory();

        const newViews = removeLayerFromView(views, currentViewId, id);

        // Also handle selection clearing if the removed layer was selected
        const newSelectedId = selectedLayerId === id ? null : selectedLayerId;

        set({
            views: newViews,
            selectedLayerId: newSelectedId
        });
    }
});
