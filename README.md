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
* [Use From Code](#use-from-code)
* [Messages From the Author](#messages-from-the-author)
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
| `--save-old-urls` | Keep old URLs working: when a product/category URL changes, its old URL becomes a 301 redirect to the new one instead of being discarded. |
| `--regen-url-key` | Also regenerate `url_key` values for the selected entity type. Category runs preserve the URL keys of cascaded products. By default `url_key` is left untouched and only `url_path`/URL Rewrites are regenerated. |
| `--no-reindex` | Skip the full reindex that normally runs at the end. |
| `--no-cache-clean` | Skip `cache:clean` at the end. |
| `--no-cache-flush` | Skip `cache:flush` at the end. |
| `--no-progress` | Hide the console progress bar. |
| `--dry-run` | Preview a run: do everything, report what would change, then roll it all back — nothing is saved, no reindex or cache refresh. See [Notes & Caveats](#notes--caveats). |
| `-v` | Print every change the run makes (URL rewrites added/removed/updated, `url_key`/`url_path` values) and a summary of them. |
| `--no-messages` | Don't show messages from the extension author at the end of the run. See [Messages From the Author](#messages-from-the-author). |
| `--delete-orphaned-rewrites` | Delete `url_rewrite` rows (for the given `--entity-type`) whose product/category no longer exists. |
| `--skip-existing` | Skip an entity entirely if it already has any URL Rewrite for the current store, instead of always deleting + regenerating. |

#### Product targeting & options
| Option | Description |
|---|---|
| `--product-id=<id>` | Regenerate for one specific product. |
| `--products-range=<from>-<to>` | Regenerate for a range of product IDs (gaps in the range are handled automatically). |
| `--include-not-visible` | Also process products with visibility "Not Visible Individually" (e.g. configurable child products) — excluded by default. See [Notes & Caveats](#notes--caveats). |
| `--add-sku-to-url` | Append the product's SKU as a URL segment, e.g. `screws.html` → `screws-2244000004.html`. Custom (admin-created) rewrites and kept old-URL redirects are left unchanged. |
| `--set-product-suffix=<suffix>` | Set the product URL suffix (e.g. `.html`) before regenerating. See [Notes & Caveats](#notes--caveats). |

#### Category targeting & options
| Option | Description |
|---|---|
| `--category-id=<id>` | Regenerate for one specific category. |
| `--categories-range=<from>-<to>` | Regenerate for a range of category IDs (gaps in the range are handled automatically). |
| `--exact-categories` | With `--category-id`/`--categories-range`: process only those categories, not all their subcategories. See [Notes & Caveats](#notes--caveats). |
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
  automatic suffix swap on existing URL Rewrites both run. A suffix locked in `app/etc/config.php` is
  reported before anything is saved; if either suffix value fails validation, the whole command aborts
  before any regeneration runs. Since the config is written *before*
  regeneration, in the same command, you get a clean
  `/categorya/oldname.html -> /categorya/newname.html` redirect instead of a two-step chain.

* **Failures and exit code**: if any product/category (or a cleanup, reindex or cache step) fails, the run
  continues with the rest, then prints a `[FAILURES]` summary (counts per type plus up to 20 of the
  failures) and exits with code `1` — after reindex and cache refresh have still run. Earlier versions always exited
  `0`, so cron jobs or scripts that check the exit code may start reporting failures that used to be
  hidden.

* **`--exact-categories`**: by default a category run also processes every subcategory of the given
  categories (a subcategory's URL path is built from its parents'). With this option only the given
  categories are processed — their subcategories only if a given category's URL path changed, and then
  without regenerating the subcategories' `url_key` (so `--regen-url-key` touches only the given
  categories). Products are regenerated only for the processed categories.

* **`--include-not-visible`** makes the run process "Not Visible Individually" products too — e.g. their
  `url_key` is regenerated with `--regen-url-key` — but it can't give them URL rewrites: Magento's own
  generator creates none for a product that isn't visible in any store view. (Since Magento 2.4.7 a product
  hidden by default but visible in some store view does get rewrites for that store view — every run
  includes it there anyway; `--store-id=0` runs need this option for it.)

* **Product `updated_at`** is left alone by a regeneration run (incl. products processed by category runs), so
  sitemap `lastmod`, delta syncs and "recently updated" reports don't see the whole catalog as changed. With
  `--regen-url-key`, a product's `updated_at` changes only when its URL key actually changes.

* **`--dry-run`** runs the whole regeneration in one database transaction and rolls it back at the end, so the
  preview is exact — even for cascades, like a parent category's new `url_key` changing its children's and
  products' URLs. It prints `Changes: …` (with `-v` every change) and `Dry run: no changes were saved.`; reindex
  and cache refresh are skipped. It can't be combined with `--set-product-suffix`/`--set-category-suffix` (Magento
  keeps a saved suffix in memory, so it couldn't be undone reliably). Rows it touches stay locked until the end, so
  admin saves of the same products/categories wait meanwhile (the storefront doesn't): preview targeted runs, or
  large ones off-peak. If a save fails inside one of Magento's own transactions (e.g. a category's `url_key`), the
  rest of that preview fails too — the failure list says so; fix the first failure and preview again.

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

## USE FROM CODE

The same run is available as a service (`@api`), e.g. for cron jobs, queue consumers or integrations —
inject `OlegKoval\RegenerateUrlRewrites\Api\RegenerateServiceInterface`:

```php
$options = $this->runOptionsBuilder // OlegKoval\RegenerateUrlRewrites\Model\RunOptionsBuilder
    ->setEntityType('product')
    ->setStoreIds([2])          // empty (default): all stores
    ->setProductIds([38, 39])   // empty (default): all products
    ->setSaveOldUrls(true)
    ->setReindex(false)         // reindex and cache refresh are on by default, as in the CLI
    ->create();

$problems = $this->regenerateService->validate($options); // string[]; run() throws InputException for these
$result = $this->regenerateService->run($options);
if ($result->hasFailures()) {
    // $result->getFailureCounts(), $result->getFailures()
}
```

`run()` accepts an optional `Api\ProgressReporterInterface` to receive progress; without one nothing is
printed. To **stop a run**, throw from the reporter's `advance()` or `message()`: the run stops after the current
entity (whose rewrites stay saved), skips the remaining stores and the reindex/cache steps, restores the current
store and rethrows your exception unchanged. The same goes for an exception from the change listener below.

To **track the run's changes** to URL rewrites and `url_key`/`url_path` values (e.g. to log, audit or undo a run),
pass an `Api\ChangeListenerInterface` as the third argument of `run()`: its `onChange()` receives an `Api\Data\ChangeInterface` per saved change —
`rewrite_added` / `rewrite_removed` / `rewrite_updated` (with the full old/new `url_rewrite` rows) and
`url_key_changed` / `url_path_changed` (with the store of the attribute row written, 0 = default scope, and
`hasOldRow()`/`hasNewRow()`, since such a row can exist and hold NULL). A rolled-back save reports nothing; without a
listener no extra queries run. The reports are complete enough to undo a run — except URL suffix changes
(`setProductUrlSuffix()`/`setCategoryUrlSuffix()`) and the suffix swap Magento then applies to existing rewrites,
which aren't reported.

To **preview a run**, `setDryRun(true)`: the run happens in one transaction that is always rolled back (no reindex
or cache refresh), and a change listener still receives every change. A dry run can't set a URL suffix, and `run()`
throws `InputException` if a database transaction is already open, since rolling back would undo the caller's work
too (e.g. in a data patch).

`$result->getProcessedCounts()` has the entities the run went through (entity type → store ID → count).
`$options->toArray()` / `$builder->fromArray($array)` log and replay a run; an unknown key or a value of the wrong
type makes `fromArray()` throw `\InvalidArgumentException`, so a typo can't widen a run to e.g. all products.

### Backward compatibility

This extension follows [Semantic Versioning](https://semver.org/). Within 1.x:

* Nothing marked `@api` is removed or renamed, and neither is any public or protected member of the command
  classes (`Console\Command\RegenerateUrlRewrites`, `RegenerateUrlRewritesAbstract`), the command name or the
  `INPUT_KEY_*` constant values; constructors only gain optional trailing arguments.
* Interfaces you implement (`Api\ProgressReporterInterface`, `Api\ChangeListenerInterface`) never gain methods —
  new hooks come as new interfaces. Interfaces only this extension implements (`Api\RegenerateServiceInterface`,
  `Api\Data\RunOptionsInterface`, `Api\Data\RunResultInterface`, `Api\Data\ChangeInterface`) may gain methods
  or optional parameters in minor releases, so don't implement them yourself (a plugin on the service is fine).
  The keys of the rewrite arrays in `ChangeInterface` stay the same.
* `Helper\Regenerate::sanitizeSkuForUrl()` (how `--add-sku-to-url` turns a SKU into a URL segment) stays, and the
  classes the command's constructor takes (`Helper\Regenerate`, `Model\RegenerateProductRewrites`,
  `Model\RegenerateCategoryRewrites`) keep their names, so subclasses calling `parent::__construct()` keep working.
* Otherwise the models (`Model\Regenerate*Rewrites`) are internal: use the service instead.

## MESSAGES FROM THE AUTHOR

The extension can show short messages from its author, such as security notes or new releases:

* **Admin:** in the notifications bell (`System > Notifications`). Only security issues use the critical
  severity that opens Magento's "Incoming Message" popup.
* **Console:** at the end of an interactive `ok:urlrewrites:regenerate` run, before the summary. Runs without a
  terminal (cron, pipes), runs with `-q` and runs with `--no-messages` show nothing.

The messages come from
[`regenerate-url-rewrites.xml`](https://github.com/olegkoval/magento-extension-notifications/blob/main/regenerate-url-rewrites.xml)
in the [magento-extension-notifications](https://github.com/olegkoval/magento-extension-notifications) repository,
read at most once a day (admin and console separately) with a 2 s timeout. A failed read is ignored and retried the
next day. The request sends only a generic user agent: no shop URL, admin URL, Magento version or other data.

To turn the messages off, set `Stores > Configuration > Advanced > System > Notifications > Regenerate URL
Rewrites Messages` to "No", or run
`bin/magento config:set system/adminnotification/olegkoval_regenerate_url_rewrites 0`.

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
