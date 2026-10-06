# Embroidery production downloads

New and regenerated embroidery jobs download an uncompressed 24-bit RGB BMP for direct import into Hatch 3: **Insert Artwork → Auto-Digitize Embroidery**. Direct BMP digitising was confirmed by the operator for order 71521.

The server requires Ghostscript. The existing composition renderer creates a temporary EPS internally, which is rasterised server-side and deleted on success or conversion failure. EPS is no longer returned as the embroidery production download. Conversion failures fail the job rather than returning EPS.

Output uses 144 DPI, a white background and disabled edge anti-aliasing, matching the tested conversion. BMP resolution metadata retains the physical scale (subject to the existing whole-point artboard rounding); check size when importing into Hatch. Output is limited to 4096 pixels per edge. Colour reduction remains a pre-approval AI-filter setting; BMP export does not impose the thread-colour setting or map thread brands.

Downloads use `image/bmp`; admin thumbnails are PNGs generated from the BMP. Historical EPS downloads remain accessible. Regenerate an existing job to obtain BMP after deployment.

The server conversion and bitmap header are covered by a Ghostscript/GD regression test. Live WordPress queue processing and newly generated plugin output in Hatch still require deployment verification.
