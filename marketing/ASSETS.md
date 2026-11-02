# Simple LDAP Login assets

Open `index.html` to view the complete set. The asset bundle is `dist/simple-ldap-login-assets.zip`. It is a media archive, not an installable plugin. In the archive, the WordPress.org folder is named `wordpress-org/` and the readmes are `readme-free.txt` and `readme-pro.txt`.

## WordPress.org

Copy the contents of `.wordpress-org/` into the top-level `assets/` directory of the WordPress.org SVN repository. That directory sits beside `trunk/` and `tags/`. Do not copy these listing images into the plugin's runtime `assets/` folder.

| Files | Size | Use |
| --- | --- | --- |
| `icon-128x128.png` | 128 × 128 | Standard icon |
| `icon-256x256.png` | 256 × 256 | High-DPI icon |
| `banner-772x250.png` | 772 × 250 | Standard banner |
| `banner-1544x500.png` | 1544 × 500 | High-DPI banner |
| `screenshot-1.jpg` through `screenshot-4.jpg` | 2240 pixels wide | Free settings, captured at 2× |

The root `readme.txt` has four matching screenshot captions. WordPress.org does not define a fixed screenshot size or a separate Retina screenshot filename. Each screenshot uses the high-resolution capture under its normal filename. Both banner sizes are supplied because the high-DPI banner needs the standard banner too. All files are below the documented size limits. [WordPress.org asset guide](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)

## PaidCommunities

The Pro paid package serves both Pro and Enterprise plans. Accounts and Audit require Enterprise.

Use `paidcommunities/` for Simple LDAP Login Pro. Its icons and banners use the same sizes as the product form currently specifies:

- **Thumbnail Image:** `thumbnail-512x512.png`. This square export is for the checkout image; the form does not specify a required thumbnail size.
- **Icons:** `icon-128x128.png` and `icon-256x256.png`.
- **Banners:** `banner-772x250.png` and `banner-1544x500.png`.
- **Readme.txt Screenshots:** upload `screenshot-1.jpg` through `screenshot-8.jpg` in order. `screenshots.json` pairs each file with its caption. Use the hosted image URLs in the product screenshot fields.
- **Pro readme:** `pro/readme.txt` is the source. The Pro build places it at the root of the plugin ZIP as `readme.txt`.

The eight refreshed screenshots were saved to Simple LDAP Login Pro on PaidCommunities on 30 October 2026. Their order and image dimensions were checked after saving. The product has no caption fields. Matching captions are in `pro/readme.txt` and `paidcommunities/screenshots.json`. The thumbnail, both icon sizes, and both banner sizes were also saved on 30 October 2026. The refreshed set shows group role rules, profile updates, scheduled account imports and access removal, and audit history with CSV export. The current product status is Published. WordPress.org assets are prepared for upload.

## Logo files and sources

`brand/` contains the transparent Free and Pro wordmarks, the transparent directory mark, a 1024-pixel mark export, and the original banner masters. Icons use the same mark for both editions. Banners and wordmarks name the correct edition.

The built-in image generation tool created the brand artwork. The exact [prompts](prompts.md) are included. Native image exports set the required dimensions. `manifest.json` records the listing image dimensions and file sizes. The PNG logo files have real alpha transparency; no SVG or editable font source is supplied.

Screenshots show the running Free plugin on the Herd OpenLDAP demo and Pro on the Samba AD Docker site. Free screenshots use a 1440 × 1000 CSS-pixel viewport at 2× scale and are 2240 pixels wide after cropping. The refreshed Pro screenshots use a 1477 × 1090 CSS-pixel viewport at 1× scale and are 1120 pixels wide after cropping. All screenshots are cropped to the `.sll-admin` settings area. All displayed directory values are public values for tests only. Password fields are empty. Pro uses an active Docker test license; the capture does not use a customer license key. The Pro SSO capture uses real Apache password authentication; its temporary demo server configuration was removed after capture.

The screenshot captions describe only implemented features. License activation and checks are implemented. The Pro readme identifies the current build as a preview because live PaidCommunities account tests and automatic Pro updates remain incomplete.

The marketing files stay outside both installable plugin ZIPs. Build those packages with `npm run build:free` and `npm run build:pro`.

The asset set uses the plugin's GPL-2.0-or-later license.
