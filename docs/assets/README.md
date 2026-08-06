# GitHub visual assets

These assets provide a consistent public identity for OverCustomise.

| File | Purpose |
| --- | --- |
| `overcustomise-mark.svg` | Square project mark for documentation and future avatars |
| `readme-banner.svg` | README hero banner |
| `architecture.svg` | High-level workflow graphic |
| `mockup-storefront.svg` | Representative storefront customiser mockup |
| `mockup-design-editor.svg` | Representative WordPress design-editor mockup |
| `mockup-print-queue.svg` | Representative print-queue mockup |
| `social-preview-vector.svg` | Optional vector composition for uses that accept SVG |
| `social-preview.png` | 1280×640 image ready for GitHub's social preview setting |

## Mockup status

The files prefixed with `mockup-` are promotional illustrations based on the plugin's current layouts, labels, controls, and visual styles. They are deliberately marked **Promo Mockup** and must not be presented as captures from a live store.

Replace or supplement them with sanitized screenshots when a public demo environment is available. Never publish customer artwork, order data, API credentials, filesystem details, or webhook secrets.

## Regenerating the social preview

The committed PNG is generated directly with PHP GD and fonts already bundled with the project's TCPDF dependencies. The optional vector composition is maintained separately and is not an input to the generator.

```bash
php scripts/generate-social-preview.php
```

To use it on GitHub, open the repository's **Settings → General → Social preview**, upload `docs/assets/social-preview.png`, and save the change.
