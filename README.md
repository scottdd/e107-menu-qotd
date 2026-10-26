# e107 Quote of the Day (`qotd_menu`) v2.6.1

A modernized **Quote of the Day** plugin for **e107 v2.3+** (compatible with **PHP 7.4 through PHP 8.4+**) with structured attributions, topical tag pills, pinned date ranges, deterministic daily or random page-load rotation, interactive AJAX **"↻ Another Quote"** button, and one-click GitHub collection sync.

Originally inspired by the classic e107 `qotd_menu` plugin by **cameron**, completely modernized for e107 v2.3+ and PHP 8.x by **Scott Dowlen**.

> *Imagined by human, built with AI.*

---

## Key Features

- **Out-of-the-Box Clean Quote Collections (`quotes/`):**
  - **`default`** (176 quotes) — Clean, family-friendly, secular general wisdom, science, programming/technology, literature, and humor quotes enabled out of the box.
  - **`spiceworks-wisdom`** (116 quotes) — Classic IT pro, sysadmin, helpdesk, and troubleshooting humor from the Spiceworks community.
  - **Companion Repository Sync ([`scottdd/qotd-collections`](https://github.com/scottdd/qotd-collections)):** One-click sync in Admin pulls the latest `default`, `personal-qotd`, and `spiceworks-wisdom` collections in both structured JSON (`structured/*.json`) and classic BSD/Linux `fortune` (`fortune/*.fortune`) formats.
- **Structured Metadata & Theme-Inheriting Tag Pills:**
  - Stores Quote Text, Attribution (Author), Attribution Context (Book / Role / Year), Topical Tags (`#wisdom`, `#humor`, `#science`, `#technology`, `#sysadmin`), Collection, and optional Source URL.
  - Tag pills automatically inherit colors and contrast from the active e107 theme (light or dark mode).
- **Pinned Quote Date Ranges:**
  - Pin any quote to a specific `Start Date → End Date` (or click **📌 Pin 7d** in Admin) so seasonal or featured quotes automatically take priority during that window.
- **Daily or Random Rotation + Interactive AJAX Button:**
  - Choose **True Quote of the Day (Daily)** or **Random on Every Page Load**, plus an optional **↻ Another Quote** button (`qotd_ajax.php`) so visitors can browse additional quotes without reloading the page.
- **Dual Data Source & Seamless Upgrade Path:**
  - Runs from the `e107_qotd` MySQL table (with full filtering, tags, and pinned dates) or directly from `.json`, `.fortune`, `.txt`, or legacy `quote.txt` files. Automatically upgrades existing `qotd_menu` v2.5 tables on install/upgrade.

---

## Screenshots

### Frontend Sidebar Widget (with Attribution, Tag Pills & "↻ Another Quote" Button)
![Frontend Widget](docs/screenshots/qotd-menu-frontend.png)

### Admin Configuration — Quotes Manager, Pinned Dates, Rotation Settings & GitHub Sync
![Admin UI](docs/screenshots/qotd-menu-admin.png)

---

## Installation

> **Important Note on Plugin Folder Name (`qotd_menu`):**
> - **Recommended:** Download **`qotd_menu_v2.6.1.zip`** from the [Releases page](https://github.com/scottdd/e107-qotd-menu/releases/latest). It is pre-packaged with the required **`qotd_menu/`** folder structure.
> - **If you use GitHub's green "Code → Download ZIP" button (`e107-qotd-menu-main.zip`) or `git clone`:** You **must** rename the extracted folder inside `e107_plugins/` to **`qotd_menu`** (or `e107_qotd_menu` — **never use hyphens** like `e107-qotd-menu-main`). e107 v2's `e107_handlers/shortcode_handler.php` constructs the PHP shortcode class name directly from the folder name (`<folder>_shortcodes`), and PHP class names cannot contain hyphens (`-`).

### Option A: One-Click Upload via e107 Admin (Recommended)
1. Download **`qotd_menu_v2.6.1.zip`** from the [latest GitHub Release](https://github.com/scottdd/e107-qotd-menu/releases/latest).
2. In your e107 Admin area, go to **Admin → Plugin Manager → Upload Plugin** and upload `qotd_menu_v2.6.1.zip`.
3. Install **Quote of the Day** (`qotd_menu`) and place `qotd_menu` in **Admin → Menu Manager**.

### Option B: Clone or Extract into `e107_plugins/`
```bash
cd /path/to/e107/e107_plugins
git clone https://github.com/scottdd/e107-qotd-menu.git qotd_menu
```
Then visit **Admin → Plugin Manager** to install **Quote of the Day**.

---

## Companion Quote Collections Repository

All bundled and optional quote collections are maintained in structured JSON and classic Linux `fortune` formats at:
- **[https://github.com/scottdd/qotd-collections](https://github.com/scottdd/qotd-collections)**

---

## Changelog

### v2.6.1 (2026-10-26)
- **Relative Path Resolution:** Replaced all hardcoded `e_PLUGIN . 'qotd_menu/...'` file includes, quote file paths, and asset/AJAX URLs with `__DIR__`-relative paths and `e107_qotd_manager::getPluginFolder()`.
- **Shortcode Handler & PHP 8.2–8.4+ Compatibility:** Added dynamic `class_alias('qotd_menu_shortcodes', '<folder>_shortcodes')` in `e_shortcode.php` so alternate valid folder names resolve cleanly on PHP 7.4 and PHP 8.x.
- **Non-English Language Fallback Hardening:** Added automatic `English_front.php` and `English_admin.php` fallback loading before `e107::includeLan()` so non-English sites never leak raw `LAN_QOTD_*` constants.

### v2.6.0 (2026-10-25)
- Complete modernization for e107 v2.3+ and PHP 8.x with structured attributions, topical tag pills, pinned date ranges, daily/random rotation, AJAX "Another Quote" button, and GitHub collection sync.

---

## Compatibility & License

- **e107 CMS:** v2.3.0+
- **PHP:** 7.4, 8.0, 8.1, 8.2, 8.3, 8.4+
- **License:** [GPL-2.0-or-later](LICENSE)

---

*Imagined by human, built with AI.*
