“Regenerate Url Rewrites” extension
=====================
Magento 2 "Regenerate Url Rewrites" extension add a CLI feature which allow regenerating a Url rewrites of products/categories in all stores or specific store.
Extension homepage: https://github.com/olegkoval/magento2-regenerate_url_rewrites

## CONTACTS
* Email: olegkoval.ca@gmail.com
* LinkedIn: https://www.linkedin.com/in/oleg-koval-85bb2314/

## DONATIONS / SUPPORT ME ON
* [PayPal](https://www.paypal.com/donate/?hosted_button_id=995MLRKBNY9QQ)
* [Patreon](https://www.patreon.com/olegkoval)

## INSTALLATION

### COMPOSER INSTALLATION
* run composer command:
>`$> composer require olegkoval/magento2-regenerate-url-rewrites`

### MANUAL INSTALLATION
* extract files from an archive

* deploy files into Magento2 folder `app/code/OlegKoval/RegenerateUrlRewrites`

### ENABLE EXTENSION
* enable extension (use Magento 2 command line interface \*):
>`$> php bin/magento module:enable OlegKoval_RegenerateUrlRewrites`

* to make sure that the enabled module is properly registered, run 'setup:upgrade':
>`$> php bin/magento setup:upgrade`

* [if needed] re-compile code and re-deploy static view files:
>`$> php bin/magento setup:di:compile`
>`$> php bin/magento setup:static-content:deploy`


## HOW TO USE IT:
* to regenerate Url Rewrites of all products in all stores (only products) set entity type to "product":
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=product`

because `product` entity type is default — you can skip it:
>`$> php bin/magento ok:urlrewrites:regenerate`

* to regenerate, Url Rewrites in the specific store view (e.g.: store view id is "2") use option `--store-id`:
>`$> php bin/magento ok:urlrewrites:regenerate --store-id=2`

* to regenerate Url Rewrites of some specific product, then use option `product-id` (e.g.: product ID is "122"):
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=product --product-id=122`

or
>`$> php bin/magento ok:urlrewrites:regenerate --product-id=122`

* to regenerate Url Rewrites of specific products range then use option `products-range` (e.g.: regenerate for all products with ID between "101" and "152"):
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=product --products-range=101-152`

\* if in the range you have a gap of ID's (in range 101-152 products with ID's 110, 124, 150 not exists) — do not worry, a script handles this.

or
>`$> php bin/magento ok:urlrewrites:regenerate --products-range=101-152`

* to save a current Url Rewrites (you want to get a new URL rewites and save current) use option `--save-old-urls`:
>`$> php bin/magento ok:urlrewrites:regenerate --save-old-urls`

* by default "url_key" values are NOT regenerated (current "url_key" values are kept); to also regenerate "url_key" values use option `--regen-url-key`:
>`$> php bin/magento ok:urlrewrites:regenerate --regen-url-key`

* if you do not want to run a full reindex at the end of Url Rewrites generation then use option `--no-reindex`:
>`$> php bin/magento ok:urlrewrites:regenerate --no-reindex`

* if you do not want to run cache:clean at the end of Url Rewrites generation then use option `--no-cache-clean`:
>`$> php bin/magento ok:urlrewrites:regenerate --no-cache-clean`

* if you do not want to run cache:flush at the end of Url Rewrites generation then use option `--no-cache-flush`:
>`$> php bin/magento ok:urlrewrites:regenerate --no-cache-flush`

* if you do not want to display a progress bar in the console then use option `--no-progress`:
>`$> php bin/magento ok:urlrewrites:regenerate --no-progress`

* to delete `url_rewrite` rows (for the given `--entity-type`) whose product/category no longer exists, use option `--delete-orphaned-rewrites`:
>`$> php bin/magento ok:urlrewrites:regenerate --delete-orphaned-rewrites`

or, for categories:
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category --delete-orphaned-rewrites`

* to skip regenerating associated product URLs when regenerating categories (faster on large catalogs when "Use Category Path for Product URLs" is enabled), use option `--skip-products`:
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category --skip-products`

Note: this option only has an effect when the "Use Category Path for Product URLs" setting
(`Stores > Configuration > Catalog > Catalog > Search Engine Optimization`, config path
`catalog/seo/product_use_categories`) is enabled — that setting is what makes category regeneration
cascade into product URLs in the first place. If it's disabled, category regeneration never touches
product URLs, with or without `--skip-products`.

* to skip an entity entirely if it already has any URL Rewrite for the current store, instead of always deleting + regenerating, use option `--skip-existing`:
>`$> php bin/magento ok:urlrewrites:regenerate --skip-existing`

* to also regenerate URLs for products with visibility "Not Visible Individually" (e.g. configurable child products, excluded by default), use option `--include-not-visible`:
>`$> php bin/magento ok:urlrewrites:regenerate --include-not-visible`

* to append the product's SKU as a URL segment in generated product Url Rewrites (e.g. `screws.html` -> `screws-2244000004.html`), use option `--add-sku-to-url`:
>`$> php bin/magento ok:urlrewrites:regenerate --add-sku-to-url`

* to set the product URL suffix (e.g. `.html`) before regenerating, use option `--set-product-suffix`:
>`$> php bin/magento ok:urlrewrites:regenerate --set-product-suffix=.html`

* to set the category URL suffix before regenerating, use option `--set-category-suffix`:
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category --set-category-suffix=.html`

Both suffix options are applied to Default Config and every store view, or only to the store given via
`--store-id`, using the same write path Magento's own `config:set` CLI command uses — so validation
(e.g. rejecting `#` or `//`) and Magento's own automatic suffix swap on existing URL Rewrites both run.
If either suffix value fails validation, the whole command aborts before any regeneration runs.

#### REGENERATE URL REWRITES OF CATEGORY
* to regenerate Url Rewrites of all categories in all stores, set an entity type to "category":
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category`

* to regenerate Url Rewrites of some specific category, then use option `category-id` (e.g.: category ID is "15"):
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category --category-id=15`

* to regenerate Url Rewrites of specific categories range then use option `categories-range` (e.g.: regenerate for all categories with ID between "4" and "12"):
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category --categories-range=4-12`

\* if in the range you have a gap of ID's (in range 4-12 category with ID "6" not exists) — do not worry, a script handles this.

\*\* If you use options `--category-id` or `--categories-range` then you can skip option `--entity-type=category` - extension will understand that you want to use a category entity.

### YOU CAN COMBINE OPTIONS
>`$> php bin/magento ok:urlrewrites:regenerate --store-id=2 --save-old-urls --regen-url-key --no-reindex`

### YOU CANNOT COMBINE THESE OPTIONS
* `--entity-type=product` and `--category-id`/`--categories-range`
* `--entity-type=category` and `--product-id`/`--products-range`
* `--category-id` and/or `--categories-range` and/or `--product-id` and/or `--products-range`

### DEPRECATED OPTIONS
* `--check-use-category-in-product-url` — extension uses a built-in Magento Url Rewrites generator which check this option in any way.
* `--no-regen-url-key` — **removed in 1.8.0** (not just deprecated — passing it will now cause an "unrecognized option" error). url_key regeneration behavior was inverted: it is now skipped by default and only runs when you explicitly pass `--regen-url-key`. If you relied on the old default (url_key regenerated automatically), add `--regen-url-key` to your existing commands/scripts/cron jobs after upgrading to 1.8.0.

### EXAMPLES OF USAGE
* Regenerate Url Rewrites for product with ID "38" in store with ID "3":
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=product --store-id=3 --product-id=38`

or
>`$> php bin/magento ok:urlrewrites:regenerate --store-id=3 --product-id=38`

* Regenerate Url Rewrites for products with ID's 5,6,7,8,9,10,11,12 in store with ID "2" and do not run full reindex at the end of process:
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=product --store-id=2 --products-range=5-12 --no-reindex`

* Regenerate Url Rewrites for category with ID "22" in all stores and save current Url Rewrites:
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category --category-id=22 --save-old-urls`

* Regenerate Url Rewrites for categories with ID's 21,22,23,24,25 in store with ID "2":
>`$> php bin/magento ok:urlrewrites:regenerate --entity-type=category --categories-range=21-25 --store-id=2`

Enjoy!

Best regards,
Oleg Koval
