# Laser Material layers

Laser Material is an **optional layer type**, not a replacement for the print
area's existing leather, glass, wood or metal engraving finish. Existing designs
remain unchanged unless a material layer is added.

## Backend setup

1. Manage the reusable colour pairs in **OverCustomise → Laser Materials**.
   Each entry has a name, surface colour, engraving colour and active status.
   Use the **Material Groups** tab to bundle related materials for assignment to
   design layers. All six starter materials are editable.
2. In the **Design Editor**, select an engraving print area and add a
   **Laser Material** layer.
   Choose **Engraving Material** in the area's engraving-material dropdown.
   This neutral finish adds no glass frosting, leather/wood texture, metallic
   shading, transparency or etched shadow. Without a material layer, artwork
   defaults to a plain dark engraving preview.
3. In **SVG Upload**, upload an SVG silhouette (maximum 256 KiB). In the
   separate **Materials** tab, select one or more material groups and choose the
   default material from the combined group contents.
4. Position and resize the layer using its normal bounds controls. Its surface
   can extend outside the engraving print area. It follows the area's rotation.
5. Place engraving text, clipart and image layers **above** the material layer.
   Artwork above it in that same area uses its selected engraving colour until
   another material layer starts a new stack. Layers below it and other print
   areas keep their existing engraving finish.
   Text (including curved/multiline text), uploaded images and all clipart use
   the selected layer's engraving colour, even clipart without an ink-colour picker.

Use a filled, static vector silhouette with transparent surroundings. SVG holes,
fill rules and alpha are preserved; the selected material recolours that alpha.
Convert text to outlines and remove embedded images and external references.
This is not a cut-line upload. Existing product-mask overlays remain on top.

Lock a material layer to use its default without showing a customer picker.
Hide the layer to remove both its surface and colour effect. Available materials
group assignments, resolved material IDs and the SVG are stored with the design
layer, so design cloning includes them. Legacy layers with direct material IDs
continue to work until they are moved to groups.

## Customer and production behaviour

Customers choose a material from paired-colour swatches. The SVG surface and
engraving artwork above it update together. Names and colours are snapshotted
with the order; later library edits cannot relabel old orders. Choices survive
design/variation switching, and server validation rejects unavailable choices.

The material surface appears in saved product previews only. It is excluded from
production render specifications: engraving remains monochrome, and the surface
does not become an engraving fill or a cutting path. Existing production clipping
is unchanged. Material colours are approximations, not laser machine settings.
