# "Regenerate Url Rewrites" extension

[![Latest Version](https://img.shields.io/packagist/v/olegkoval/magento2-regenerate-url-rewrites.svg)](https://packagist.org/packages/olegkoval/magento2-regenerate-url-rewrites)
[![Total Downloads](https://img.shields.io/packagist/dt/olegkoval/magento2-regenerate-url-rewrites.svg)](https://packagist.org/packages/olegkoval/magento2-regenerate-url-rewrites)
[![License](https://img.shields.io/badge/license-OSL--3.0%20%7C%20AFL--3.0-blue.svg)](#license)

A Magento 2 CLI extension that regenerates URL Rewrites for products and categories — across all
stores, a single store view, or a specific ID/range — with options to preserve old URLs, delete
orphaned rewrites, change the URL suffix, and more.

Extension homepage: https://github.com/olegkoval/magento2-regenerate_url_rewrites
Changelog: [CHANGELOG.md](CHANGELOG.md)

## Table of Contents
* [Requirements](#requirements)
* [Installation](#installation)
* [Quick Start](#quick-start)
* [CLI Options Reference](#cli-options-reference)
* [Notes & Caveats](#notes--caveats)
* [Combining Options](#combining-options)
* [Deprecated Options](#deprecated-options)
* [More Examples](#more-examples)
* [Support Me](#support-me)
* [Contacts](#contacts)
* [License](#license)

## REQUIREMENTS

| Magento Open Source (CE) | PHP      |
|--------------------------|----------|
| 2.4.7                    | 8.2, 8.3 |
| 2.4.8                    | 8.3, 8.4 |
| 2.4.9 (latest)           | 8.4, 8.5 |

`composer.json` declares `"php": ">=8.2"`. PHP compatibility otherwise follows whatever your
Magento version itself requires — the extension doesn't impose a narrower floor than Magento does.
2.4.6 and older are treated as legacy/best-effort only (no longer patched upstream by Adobe).

## INSTALLATION

### COMPOSER INSTALLATION
* run composer command:
>`$> composer require olegkoval/magento2-regenerate-url-rewrites`

### MANUAL INSTALLATION
* extract files from an archive

* deploy files into Magento2 folder `app/code/OlegKoval/RegenerateUrlRewrites`

### ENABLE EXTENSION
* enable extension (use Magento 2 command line interface):
>`$> php bin/magento module:enable OlegKoval_RegenerateUrlRewrites`

* to make sure that the enabled module is properly registered, run 'setup:upgrade':
>`$> php bin/magento setup:upgrade`

* [if needed] re-compile code and re-deploy static view files:
>`$> php bin/magento setup:di:compile`
>`$> php bin/magento setup:static-content:deploy`

## QUICK START

* regenerate Url Rewrites for **all products**, in **all stores** (`product` is the default entity type, so it can be omitted):
>`$> php bin/magento ok:urlrewrites:regenerate`

* regenerate Url Rewrites for **all categories**, in **all stores**:
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category`

* regenerate Url Rewrites for **one product** (ID `122`), in **one store** (ID `2`):
>`$> php bin/magento ok:urlrewrites:regenerate --product-id=122 --store-id=2`

Keep reading for the full options reference, or jump to [More Examples](#more-examples).

## CLI OPTIONS REFERENCE

#### General
| Option | Description |
|---|---|
| `--entity-type=<product\|category>` | Entity type to regenerate. Default: `product`. Can be omitted when `--category-id`/`--categories-range` is given — the extension infers `category` automatically. |
| `--store-id=<id>` | Regenerate only for the given store view. Omit to run for all stores. |
| `--save-old-urls` | Keep the current URL Rewrites (as 301 history) instead of discarding them when new ones are generated. |
| `--regen-url-key` | Also regenerate `url_key` values. By default `url_key` is left untouched and only `url_path`/URL Rewrites are regenerated. |
| `--no-reindex` | Skip the full reindex that normally runs at the end. |
| `--no-cache-clean` | Skip `cache:clean` at the end. |
| `--no-cache-flush` | Skip `cache:flush` at the end. |
| `--no-progress` | Hide the console progress bar. |
| `--delete-orphaned-rewrites` | Delete `url_rewrite` rows (for the given `--entity-type`) whose product/category no longer exists. |
| `--skip-existing` | Skip an entity entirely if it already has any URL Rewrite for the current store, instead of always deleting + regenerating. |

#### Product targeting & options
| Option | Description |
|---|---|
| `--product-id=<id>` | Regenerate for one specific product. |
| `--products-range=<from>-<to>` | Regenerate for a range of product IDs (gaps in the range are handled automatically). |
| `--include-not-visible` | Also regenerate URLs for products with visibility "Not Visible Individually" (e.g. configurable child products) — excluded by default. |
| `--add-sku-to-url` | Append the product's SKU as a URL segment, e.g. `screws.html` → `screws-2244000004.html`. |
| `--set-product-suffix=<suffix>` | Set the product URL suffix (e.g. `.html`) before regenerating. See [Notes & Caveats](#notes--caveats). |

#### Category targeting & options
| Option | Description |
|---|---|
| `--category-id=<id>` | Regenerate for one specific category. |
| `--categories-range=<from>-<to>` | Regenerate for a range of category IDs (gaps in the range are handled automatically). |
| `--skip-products` | Skip regenerating associated product URLs when regenerating categories. See [Notes & Caveats](#notes--caveats). |
| `--set-category-suffix=<suffix>` | Set the category URL suffix (e.g. `.html`) before regenerating. See [Notes & Caveats](#notes--caveats). |

## NOTES & CAVEATS

* **`--skip-products`** only has an effect when the "Use Category Path for Product URLs" setting
  (`Stores > Configuration > Catalog > Catalog > Search Engine Optimization`, config path
  `catalog/seo/product_use_categories`) is enabled — that setting is what makes category
  regeneration cascade into product URLs in the first place. If it's disabled, category
  regeneration never touches product URLs, with or without `--skip-products`.

* **`--set-product-suffix` / `--set-category-suffix`** are applied to Default Config and every
  store view, or only to the store given via `--store-id`, using the same write path Magento's own
  `config:set` CLI command uses — so validation (e.g. rejecting `#` or `//`) and Magento's own
  automatic suffix swap on existing URL Rewrites both run. If either suffix value fails validation,
  the whole command aborts before any regeneration runs. Since the config is written *before*
  regeneration, in the same command, you get a clean
  `/categorya/oldname.html -> /categorya/newname.html` redirect instead of a two-step chain.

* **Failures and exit code**: if any product/category (or a cleanup step) fails, the run continues with
  the rest, then prints a `[FAILURES]` summary (counts per entity type plus up to 20 of the failures) and
  exits with code `1` — after reindex and cache refresh have still run. Earlier versions always exited
  `0`, so cron jobs or scripts that check the exit code may start reporting failures that used to be
  hidden.

* **`--regen-url-key`** inverted its default behavior in 1.8.0: `url_key` is no longer regenerated
  automatically. Pass `--regen-url-key` explicitly whenever you want it regenerated too — see
  [Deprecated Options](#deprecated-options) if you're upgrading from an older version.

## COMBINING OPTIONS

Most options combine freely, e.g.:
>`$> php bin/magento ok:urlrewrites:regenerate --store-id=2 --save-old-urls --regen-url-key --no-reindex`

**These combinations are not allowed:**
* `--entity-type=product` together with `--category-id` / `--categories-range`
* `--entity-type=category` together with `--product-id` / `--products-range`
* `--category-id` and/or `--categories-range` together with `--product-id` and/or `--products-range`

## DEPRECATED OPTIONS

* `--check-use-category-in-product-url` — obsolete. The extension now uses Magento's built-in URL
  Rewrite generator, which already checks the "Use Category Path for Product URLs" config on its
  own, so this manual flag is no longer needed.
* `--no-regen-url-key` — **removed in 1.8.0** (not just deprecated — passing it now causes an
  "unrecognized option" error). `url_key` regeneration behavior was inverted: it is now skipped by
  default and only runs when you explicitly pass `--regen-url-key`. If you relied on the old
  default (`url_key` regenerated automatically), add `--regen-url-key` to your existing
  commands/scripts/cron jobs after upgrading to 1.8.0.

## MORE EXAMPLES

* Regenerate Url Rewrites for product with ID `38` in store with ID `3`:
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=product --store-id=3 --product-id=38`

or
>`$> php bin/magento ok:urlrewrites:regenerate --store-id=3 --product-id=38`

* Regenerate Url Rewrites for products with IDs 5,6,7,8,9,10,11,12 in store with ID `2` and skip the full reindex:
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=product --store-id=2 --products-range=5-12 --no-reindex`

* Regenerate Url Rewrites for category with ID `22` in all stores and save current Url Rewrites:
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category --category-id=22 --save-old-urls`

* Regenerate Url Rewrites for categories with IDs 21,22,23,24,25 in store with ID `2`:
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category --categories-range=21-25 --store-id=2`

* Set the category URL suffix to `.html` and regenerate all category URLs in one step:
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category --set-category-suffix=.html`

## SUPPORT ME

* [Ko-fi](https://ko-fi.com/olegkoval77)
* [PayPal](https://www.paypal.com/donate/?hosted_button_id=995MLRKBNY9QQ)
* [Patreon](https://www.patreon.com/olegkoval)

## CONTACTS

* Email: olegkoval.ca@gmail.com
* LinkedIn: https://www.linkedin.com/in/oleg-koval-85bb2314/
* Issues & feature requests: [GitHub Issues](https://github.com/olegkoval/magento2-regenerate_url_rewrites/issues)

## LICENSE

Dual-licensed, same as Magento itself:
* [Open Software License (OSL-3.0)](LICENSE.txt)
* [Academic Free License (AFL-3.0)](LICENSE_AFL.txt)

Enjoy!

Best regards,
Oleg Koval
