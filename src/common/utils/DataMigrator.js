export const migrateData = (data) => {
    if (!data) return {};

    // Clone to avoid mutation side-effects during migration
    const clean = JSON.parse(JSON.stringify(data));

    // STRICT SCHEMA V2:
    // {
    //    userInputs: { [layerId]: "value" },
    //    userStyles: { [layerId]: { property: "value" } },
    //    views: [ ... ],
    //    embroideryColor: { ... }
    // }

    // Case 1: Legacy Flat "inputs" (Old v1)
    // Sometimes data IS the input map directly? Or data.inputs?
    // Let's assume 'data' is the object passed to setConfig or hydrated from JSON.

    // If data has 'inputs' and 'styles' (Intermediate v1.5)
    // Convert to userInputs / userStyles keys
    if (clean.inputs && !clean.userInputs) {
        clean.userInputs = clean.inputs;
        delete clean.inputs;
    }
    if (clean.styles && !clean.userStyles) {
        clean.userStyles = clean.styles;
        delete clean.styles;
    }

    // Case 2: Deeply Nested 'userInputs.inputs' (Legacy Bug)
    // Some versions saved the entire store state into userInputs?
    if (clean.userInputs && clean.userInputs.inputs) {
        // Hoist them up
        const realInputs = clean.userInputs.inputs;
        const realStyles = clean.userInputs.styles || {};
        const realEmbroidery = clean.userInputs.embroideryColor || null;

        clean.userInputs = realInputs;
        // Merge styles if not present
        if (!clean.userStyles) clean.userStyles = realStyles;
        if (!clean.embroideryColor) clean.embroideryColor = realEmbroidery;
    }

    // Case 3: Missing Objects
    if (!clean.userInputs) clean.userInputs = {};
    if (!clean.userStyles) clean.userStyles = {};

    return clean;
};
