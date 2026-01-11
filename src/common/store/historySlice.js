export const createHistorySlice = (set, get, config = {}) => {
    // Config can define what parts of state to snapshot.
    // Default: snapshot 'views', 'userInputs', 'userStyles'
    const keysToSnapshot = config.keys || ['views', 'userInputs', 'userStyles'];

    return {
        past: [],
        future: [],

        saveHistory: () => set((state) => {
            const snapshot = {};
            keysToSnapshot.forEach(key => {
                if (state[key] !== undefined) {
                    snapshot[key] = JSON.parse(JSON.stringify(state[key]));
                }
            });

            return {
                past: [...state.past, snapshot].slice(-20), // Limit 20
                future: []
            };
        }),

        undo: () => set((state) => {
            if (state.past.length === 0) return {};

            const previous = state.past[state.past.length - 1];
            const newPast = state.past.slice(0, state.past.length - 1);

            // Create current snapshot for Redo stack
            const currentSnapshot = {};
            keysToSnapshot.forEach(key => {
                if (state[key] !== undefined) {
                    currentSnapshot[key] = JSON.parse(JSON.stringify(state[key]));
                }
            });

            return {
                past: newPast,
                future: [currentSnapshot, ...state.future],
                ...previous, // Restore state
                a11yMessage: 'Undid last change.'
            };
        }),

        redo: () => set((state) => {
            if (state.future.length === 0) return {};

            const next = state.future[0];
            const newFuture = state.future.slice(1);

            // Create current snapshot for Undo stack
            const currentSnapshot = {};
            keysToSnapshot.forEach(key => {
                if (state[key] !== undefined) {
                    currentSnapshot[key] = JSON.parse(JSON.stringify(state[key]));
                }
            });

            return {
                past: [...state.past, currentSnapshot],
                future: newFuture,
                ...next, // Restore state
                a11yMessage: 'Redid last change.'
            };
        })
    };
};
