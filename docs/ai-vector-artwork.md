# AI filter vector artwork

For embroidery filters, set **Image Filters → Vector colours** to the maximum number of flat colours (for example, `3`). `0` retains the existing raster output. Use **Run Test** to inspect the processed result. Enable **Remove background** for a plain background; the vector mode removes edge-connected background rather than converting the whole image's brightness into opacity, so enclosed white details remain printable.

The result is quantised without dithering, despeckled and traced into simplified SVG compound paths. That **same saved SVG** is returned for the customer preview and used by the embroidery renderer. Production downloads are now BMPs, rasterised server-side from the composition; see [Embroidery BMP downloads](embroidery-bmp.md). Palette reduction and tracing never happen for the first time after checkout. Changing vector settings starts a separate result history, so previously generated raster results are not offered as new vector proofs.

Colours are derived from the image, not mapped to named thread products. The setting is a maximum, not a guarantee that every requested colour occurs. Specify actual colours in the AI prompt instead of `[Insert Color 1]` placeholders. Asking an image model for a “vector” aesthetic does not itself generate vector geometry.

The tracer uses PHP GD, with no external tracing service or executable. It limits its working edge to 1024 pixels and merges small connected colour regions into their neighbours before tracing (under 24 pixels at that resolution, scaled down with a four-pixel floor for smaller images). It drops remaining islands/holes smaller than four working pixels and simplifies boundaries with a 0.65-pixel tolerance. It rejects artwork above 256 actual contours or its other outline limits rather than falling back to pixel rectangles. Soft transparency is resolved against white before tracing and approval. These are artwork vectors for digitising, not embroidery stitch instructions.

Count contours when assessing complexity: one SVG colour path or EPS compound fill can contain hundreds of separate contours that an editor imports as individual objects. A colour count is **not** an imported-object count. The v2 processing-history key keeps older, more fragmented vector results separate from newly traced proofs.

## Existing orders

Existing approved attachments and EPS files are not rewritten. Reprocess the retained original AI PNG, inspect the new vector proof, obtain approval for any changed details, and then regenerate production artwork from that proof. Details already dropped by an older EPS export cannot be recovered from that EPS.

The investigated order 71521 EPS contained 34,351 `rectfill` commands and 11,519 distinct RGB colours. Its transparent raster exporter used a half-opacity cutoff and made surviving pixels fully opaque. This explains the object explosion and is consistent with the missing pale steam and darker pot. Comparing the original generated PNG is still needed to verify that order's exact pixel differences.
