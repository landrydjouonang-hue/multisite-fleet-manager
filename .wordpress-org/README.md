# WordPress.org assets

This folder is **not shipped** with the plugin (see `.distignore`). It holds the listing assets that WordPress.org serves from the `/assets/` folder of an SVN repository, and that the standard deploy actions pick up from `.wordpress-org/`.

## Files to add here

| File | Size | Notes |
|---|---|---|
| `icon-128x128.png` | 128×128 | Plugin icon |
| `icon-256x256.png` | 256×256 | Retina icon |
| `banner-772x250.png` | 772×250 | Header banner |
| `banner-1544x500.png` | 1544×500 | Retina banner |
| `screenshot-1.png` … `screenshot-8.png` | 1200×900 or wider | In the order listed under `== Screenshots ==` in `readme.txt` |

PNG or JPG only; WordPress.org does not accept SVG for these. An SVG source for the icon is in `../assets-source/icon.svg` — export it at both sizes.

## Screenshot order

The captions come from `readme.txt`, so the files must match that order:

1. Site Dashboard — the fleet console with a filter applied and one row expanded
2. Dashboard — status counts, network facts and recent activity
3. Health — the twenty-one indicators grouped by concern
4. Plugins & Themes
5. Users
6. Reports
7. Operation Log — including a refused entry
8. Settings — with delegated access visible

Capture them on a network with a mix of health states (see `../DEMO.md` for how to set one up), and blur real site names and e-mail addresses.
