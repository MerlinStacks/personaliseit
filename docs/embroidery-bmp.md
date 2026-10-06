# Embroidery production downloads

New and regenerated embroidery jobs download an uncompressed 24-bit RGB BMP for direct import into Hatch 3: **Insert Artwork → Auto-Digitize Embroidery**. Direct BMP digitising was confirmed by the operator for order 71521.

The server needs **PHP GD with BMP support or Ghostscript**. Ghostscript is preferred when available. If it is missing or PHP process execution (`proc_open`) is disabled, generation automatically uses a PHP/GD renderer. Both backends use the existing composition renderer's temporary drawing file, which is deleted on success or conversion failure. EPS is no longer returned as the embroidery production download. Conversion failures fail the job rather than returning EPS.

The GD fallback supports uploaded TrueType font outlines, text wrapping and alignment, layer order and rotation, line art, common SVG paths/shapes, raster images, image filters, cropping and circle/oval masks. It preserves glyph holes and SVG fill rules. Text without a selected font uses the bundled DejaVu Sans font in the fallback. Complex SVG features outside the built-in vector renderer still need a working Imagick SVG rasteriser or a PNG derivative. The fallback does not interpret arbitrary EPS or silently omit unsupported artwork. Both renderers retain the existing restrictions on PDF/EPS originals without safe derivatives.

Output uses 144 DPI, a white background and disabled edge anti-aliasing, matching the tested conversion. BMP resolution metadata retains the physical scale (subject to the existing whole-point artboard rounding); check size when importing into Hatch. Output is limited to 4096 pixels per edge. Colour reduction remains a pre-approval AI-filter setting; BMP export does not impose the thread-colour setting or map thread brands.

Downloads use `image/bmp`; admin thumbnails are PNGs generated from the BMP. Historical EPS downloads remain accessible. Regenerate an existing job to obtain BMP after deployment.

Both backends have bitmap-header regression coverage. GD tests also check rendered pixels, winding holes, clipping, transforms, curves, strokes, transparent artwork and text, including automatic generation with `proc_open` disabled. Live WordPress queue processing and newly generated plugin output in Hatch still require deployment verification.

## Server setup

For shared hosting, enable the PHP GD extension with BMP support for the PHP worker running WordPress, deploy the updated plugin, then regenerate the failed print file. Ghostscript and `proc_open` are not required for the GD fallback.

To use the preferred Ghostscript backend, install Ghostscript on the server (or inside the PHP container) that runs WordPress. On Debian/Ubuntu, the hosting administrator can run `sudo apt-get install ghostscript`. PHP must have `proc_open` enabled, and its worker user must be allowed to execute Ghostscript. A successful `gs --version` in an SSH session alone does not confirm availability to PHP-FPM.

For a non-standard installation, configure the executable path in a site-specific plugin or MU plugin:

```php
add_filter( 'oc_ghostscript_binary_candidates', static function ( array $paths ): array {
    array_unshift( $paths, '/opt/ghostscript/bin/gs' );
    return $paths;
} );
```

Replace that example with the actual executable path. Both System Status and embroidery conversion honour this filter. Confirm Ghostscript is available in System Status, then regenerate the failed print file.
