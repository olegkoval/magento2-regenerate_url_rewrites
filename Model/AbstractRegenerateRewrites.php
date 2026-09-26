<?php
/**
 * AbstractRegenerateRewrites.php
 *
 * @package OlegKoval_RegenerateUrlRewrites
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2017-2067 Oleg Koval
 * @license OSL-3.0, AFL-3.0
 */

namespace OlegKoval\RegenerateUrlRewrites\Model;

use OlegKoval\RegenerateUrlRewrites\Helper\Regenerate as RegenerateHelper;
use Magento\Framework\App\ResourceConnection;
use Magento\UrlRewrite\Model\Storage\DbStorage;
use Magento\CatalogUrlRewrite\Model\ResourceModel\Category\Product as ProductUrlRewriteResource;

abstract class AbstractRegenerateRewrites
{
    /**
     * @var string
     */
    protected $entityType = 'product';

    /**
     * @var array
     */
    protected $storeRootCategoryId = [];

    /**
     * @var integer
     */
    protected $progressBarProgress = 0;

    /**
     * @var integer
     */
    protected $progressBarTotal = 0;

    /**
     * @var string
     */
    protected $mainDbTable;

    /**
     * @var string
     */
    protected $secondaryDbTable;

    /**
     * @var string
     */
    protected $categoryProductsDbTable;

    /**
     * Regenerate Rewrites custom options
     * @var array
     */
    protected $regenerateOptions = [];

    /**
     * Default regenerate options, merged under whatever setRegenerateOptions() receives
     * @var array
     */
    protected array $defaultRegenerateOptions = [
        'saveOldUrls' => false,
        'categoriesFilter' => [],
        'productsFilter' => [],
        'categoryId' => null,
        'productId' => null,
        'regenUrlKey' => false,
        'showProgress' => false,
        'skipProducts' => false,
        'skipExisting' => false,
        'includeNotVisible' => false,
        'addSkuToUrl' => false,
    ];

    /**
     * Max failure details retained; beyond it only counts grow, so a systemic error on a large catalog
     * can't exhaust memory
     */
    protected const FAILURE_DETAILS_LIMIT = 1000;

    /**
     * First FAILURE_DETAILS_LIMIT failures collected since the last resetFailures() call
     * @var array<int, array{entity_type: string, entity_id: int|null, store_id: int|null, message: string}>
     */
    protected array $failures = [];

    /**
     * Exact failure count per entity type since the last resetFailures() call
     * @var array<string, int>
     */
    protected array $failureCounts = [];

    /**
     * @var RegenerateHelper
     */
    protected $helper;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * RegenerateAbstract constructor
     *
     * @param RegenerateHelper $helper
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        RegenerateHelper   $helper,
        ResourceConnection $resourceConnection
    )
    {
        $this->helper = $helper;
        $this->resourceConnection = $resourceConnection;
        $this->regenerateOptions = $this->defaultRegenerateOptions;
    }

    /**
     * Set regenerate options; keys not given fall back to the defaults
     *
     * Merges over the defaults rather than the current options, since model instances are shared
     * (e.g. the category model re-sets the product model's options per category) and a previous
     * call's keys must not leak into the next one.
     *
     * @param array $options
     * @return void
     */
    public function setRegenerateOptions(array $options): void
    {
        $this->regenerateOptions = array_merge($this->defaultRegenerateOptions, $options);
    }

    abstract function regenerate(int $storeId = 0);

    /**
     * First FAILURE_DETAILS_LIMIT failures since the last resetFailures() call (see getFailureCounts() for totals)
     *
     * @return array<int, array{entity_type: string, entity_id: int|null, store_id: int|null, message: string}>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * Exact failure count per entity type since the last resetFailures() call
     *
     * @return array<string, int>
     */
    public function getFailureCounts(): array
    {
        return $this->failureCounts;
    }

    /**
     * Clear collected failures
     *
     * Never called from inside a run: the command calls regenerate() once per store and the category
     * model reuses the product model per category, so an implicit reset would drop earlier failures.
     *
     * @return $this
     */
    public function resetFailures(): static
    {
        $this->failures = [];
        $this->failureCounts = [];

        return $this;
    }

    /**
     * @param string $entityType
     * @param int|null $entityId null for a failure not tied to a single entity (e.g. a cleanup step)
     * @param int|null $storeId
     * @param string $message
     * @return void
     */
    protected function _addFailure(string $entityType, ?int $entityId, ?int $storeId, string $message): void
    {
        $this->failureCounts[$entityType] = ($this->failureCounts[$entityType] ?? 0) + 1;

        if (count($this->failures) >= static::FAILURE_DETAILS_LIMIT) {
            return;
        }

        $this->failures[] = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'store_id' => $storeId,
            'message' => $message,
        ];
    }

    /**
     * Return resource connection
     * @return ResourceConnection
     */
    protected function _getResourceConnection(): ResourceConnection
    {
        return $this->resourceConnection;
    }

    /**
     * Save Url Rewrites
     *
     * @param array $urlRewrites
     * @param array $entityData
     * @return $this
     */
    public function saveUrlRewrites(array $urlRewrites, array $entityData = []): static
    {
        $data = $this->_prepareUrlRewrites($urlRewrites);

        // every generated path was empty: nothing to write, so keep the current rewrites (an empty
        // insertOnDuplicate() builds column-less SQL, which either fails or commits after the delete)
        if (empty($data)) {
            return $this;
        }

        if (empty($entityData)) {
            $entityData = $data;
        }

        // the generated set replaces the entity's current rows even with --save-old-urls, as Magento's
        // own UrlPersist does: the generator has already turned each old URL into a 301 (save_rewrites_history)
        // and re-emitted custom rows. Keeping the old rows instead made each 301 collide with its old row on
        // the unique key, and insertOnDuplicate() only updates request_path/metadata, so the 301 was lost and
        // the old URL kept serving the page (see #174, #142).
        // delete + insert run in a single transaction so a failed insert can't leave an entity
        // with its old rewrites deleted and nothing to replace them (see #137)
        $connection = $this->_getResourceConnection()->getConnection();
        $connection->beginTransaction();
        try {
            $this->_deleteCurrentRewrites($entityData);

            $connection->insertOnDuplicate(
                $this->_getMainTableName(),
                $data,
                ['request_path', 'metadata']
            );
            $connection->commit();

        } catch (\Exception $e) {
            $connection->rollBack();

            // one failure per entity whose rewrites were not saved (a category call can include children)
            $failed = [];
            foreach ($entityData ?: $data as $row) {
                $failed[$row['entity_type'] . '|' . $row['entity_id'] . '|' . $row['store_id']] = $row;
            }
            foreach ($failed as $row) {
                $this->_addFailure(
                    (string)$row['entity_type'],
                    (int)$row['entity_id'],
                    (int)$row['store_id'],
                    'saving URL rewrites failed: ' . $e->getMessage()
                );
            }
        }

        return $this;
    }

    /**
     * Show a progress bar in the console
     *
     * @param int $size
     */
    protected function _showProgress(int $size = 70): void
    {
        if (!$this->regenerateOptions['showProgress']) {
            return;
        }

        if ($this->progressBarTotal === 0) {
            echo "\r[" . str_repeat('=', $size + 1) . "] 100%  0/0\r\n";
            flush();
            return;
        }

        // if we go over our bound, just ignore it
        if ($this->progressBarProgress > $this->progressBarTotal) {
            return;
        }

        $perc = $this->progressBarTotal ? (float)($this->progressBarProgress / $this->progressBarTotal) : 1;
        $bar = floor($perc * $size);

        $status_bar = "\r[";
        $status_bar .= str_repeat('=', $bar);
        if ($bar < $size) {
            $status_bar .= '>';
            $status_bar .= str_repeat(' ', $size - $bar);
        } else {
            $status_bar .= '=';
        }

        $disp = number_format($perc * 100, 0);

        $status_bar .= "] {$disp}%  {$this->progressBarProgress}/{$this->progressBarTotal}";

        echo $status_bar;
        flush();

        // when done, send a newline
        if ($this->progressBarProgress == $this->progressBarTotal) {
            echo "\r\n";
        }
    }

    /**
     * @return string
     */
    protected function _getMainTableName(): string
    {
        if (empty($this->mainDbTable)) {
            $this->mainDbTable = $this->_getResourceConnection()->getTableName(DbStorage::TABLE_NAME);
        }

        return $this->mainDbTable;
    }

    /**
     * @return string
     */
    protected function _getSecondaryTableName(): string
    {
        if (empty($this->secondaryDbTable)) {
            $this->secondaryDbTable = $this->_getResourceConnection()->getTableName(ProductUrlRewriteResource::TABLE_NAME);
        }

        return $this->secondaryDbTable;
    }

    /**
     * @return string
     */
    protected function _getCategoryProductsTableName(): string
    {
        if (empty($this->categoryProductsDbTable)) {
            $this->categoryProductsDbTable = $this->_getResourceConnection()->getTableName('catalog_category_product');
        }

        return $this->categoryProductsDbTable;
    }

    /**
     * Delete current Url Rewrites
     *
     * @param array $entitiesData
     * @return $this
     */
    protected function _deleteCurrentRewrites(array $entitiesData = []): static
    {
        if (!empty($entitiesData)) {
            $whereConditions = [];
            $connection = $this->_getResourceConnection()->getConnection();
            foreach ($entitiesData as $entityData) {
                $whereConditions[] = '('
                    . $connection->quoteInto('entity_type = ?', $entityData['entity_type'])
                    . $connection->quoteInto(' AND entity_id = ?', (int)$entityData['entity_id'])
                    . $connection->quoteInto(' AND store_id = ?', (int)$entityData['store_id'])
                    . ')';
            }
            $whereConditions = array_unique($whereConditions);

            // transaction is managed by the caller (saveUrlRewrites()), so delete + insert commit
            // or roll back together (see #137)
            $connection->delete(
                $this->_getMainTableName(),
                implode(' OR ', $whereConditions)
            );
        }

        return $this;
    }

    /**
     * Delete url_rewrite rows (for this entity type) whose product/category no longer exists (see #158)
     *
     * @return $this
     */
    public function deleteOrphanedRewrites(): static
    {
        $entityTable = $this->_getResourceConnection()->getTableName(
            $this->entityType === 'category' ? 'catalog_category_entity' : 'catalog_product_entity'
        );

        $connection = $this->_getResourceConnection()->getConnection();
        $connection->beginTransaction();
        try {
            $connection->delete(
                $this->_getMainTableName(),
                [
                    'entity_type = ?' => $this->entityType,
                    "entity_id NOT IN (SELECT entity_id FROM {$entityTable})",
                ]
            );
            $connection->commit();

        } catch (\Exception $e) {
            $connection->rollBack();
            $this->_addFailure($this->entityType, null, null, 'deleting orphaned rewrites failed: ' . $e->getMessage());
        }

        return $this;
    }

    /**
     * Update "catalog_url_rewrite_product_category" table
     *
     * @return $this
     */
    protected function _updateSecondaryTable(): static
    {
        $this->_getResourceConnection()->getConnection()->beginTransaction();
        try {
            $this->_getResourceConnection()->getConnection()->delete(
                $this->_getSecondaryTableName(),
                "url_rewrite_id NOT IN (SELECT url_rewrite_id FROM {$this->_getMainTableName()})"
            );
            $this->_getResourceConnection()->getConnection()->commit();

        } catch (\Exception $e) {
            $this->_getResourceConnection()->getConnection()->rollBack();
            $this->_addFailure(
                $this->entityType,
                null,
                null,
                'cleaning the product/category rewrite table failed: ' . $e->getMessage()
            );
        }

        $select = $this->_getResourceConnection()->getConnection()->select()
            ->from(
                $this->_getMainTableName(),
                [
                    'url_rewrite_id',
                    'category_id' => new \Zend_Db_Expr(
                        'SUBSTRING_INDEX(SUBSTRING_INDEX(' . $this->_getMainTableName() . '.metadata, \'"\', -2), \'"\', 1)'
                    ),
                    'product_id' => 'entity_id'
                ]
            )
            ->where('metadata LIKE \'{"category_id":"%"}\'')
            ->where("url_rewrite_id NOT IN (SELECT url_rewrite_id FROM {$this->_getSecondaryTableName()})");
        try {
            $data = $this->_getResourceConnection()->getConnection()->fetchAll($select);
        } catch (\Exception $e) {
            $this->_addFailure(
                $this->entityType,
                null,
                null,
                'reading rewrites for the product/category rewrite table failed: ' . $e->getMessage()
            );

            return $this;
        }

        if (!empty($data)) {
            // I'm using row-by-row inserts because some products/categories not exists in entity tables but Url Rewrites
            // for this entity still exists in url_rewrite DB table.
            // This is the issue of Magento EE (Data integrity/assurance of the accuracy and consistency of data),
            // and this extension was made to not fix this; I just avoid this issue
            foreach ($data as $row) {
                $this->_getResourceConnection()->getConnection()->beginTransaction();
                try {
                    $this->_getResourceConnection()->getConnection()->insertOnDuplicate(
                        $this->_getSecondaryTableName(),
                        $row,
                        ['product_id']
                    );
                    $this->_getResourceConnection()->getConnection()->commit();

                } catch (\Exception $e) {
                    $this->_getResourceConnection()->getConnection()->rollBack();
                }
            }
        }

        return $this;
    }

    /**
     * @param array $urlRewrites
     * @return array
     */
    protected function _prepareUrlRewrites(array $urlRewrites): array
    {
        $result = [];
        $preserved = [];
        // "store|path" of preserved rows: a generated path must not take one of them over
        $reservedPaths = [];
        // "store|path Magento generated" => path it was actually saved under (clean-up, dedup index)
        $finalPaths = [];

        // non-autogenerated rows are admin-created custom rewrites or old-URL redirects kept by
        // --save-old-urls, re-emitted by Magento's generator: their request path must stay exactly
        // as it is (no sanitizing, no dedup index), or the URL they exist for is lost
        foreach ($urlRewrites as $urlRewrite) {
            if (!$urlRewrite->getIsAutogenerated()) {
                $rewrite = $urlRewrite->toArray();
                $preserved[] = $rewrite;
                $reservedPaths[$rewrite['store_id'] . '|' . $rewrite['request_path']] = true;
            }
        }

        foreach ($urlRewrites as $urlRewrite) {
            if (!$urlRewrite->getIsAutogenerated()) {
                continue;
            }
            $rewrite = $urlRewrite->toArray();

            // check if the same Url Rewrite already exists
            $originalRequestPath = trim($rewrite['request_path']);

            // skip empty Url Rewrites - I don't know how this possible, but it happens in Magento:
            // maybe someone did import product programmatically and product(s) name(s) are empty
            if (empty($originalRequestPath)) continue;

            // split generated Url Rewrite into parts
            $pathParts = pathinfo($originalRequestPath);

            // remove leading/trailing slashes and dots from parts
            $pathParts['dirname'] = trim($pathParts['dirname'], './');
            $pathParts['filename'] = trim($pathParts['filename'], './');

            // If the last symbol was slash - let's use it as url suffix
            $urlSuffix = substr($originalRequestPath, -1) === '/' ? '/' : '';

            // re-set Url Rewrite with sanitized parts
            $rewrite['request_path'] = $this->_mergePartsIntoRewriteRequest($pathParts, '', $urlSuffix);

            // check if we have a duplicate (maybe exists product with the same name => same Url Rewrite)
            // if exists then add additional index to avoid a duplicates
            $index = 0;
            while (
                isset($reservedPaths[$rewrite['store_id'] . '|' . $rewrite['request_path']])
                || $this->_urlRewriteExists($rewrite)
            ) {
                $index++;
                $rewrite['request_path'] = $this->_mergePartsIntoRewriteRequest($pathParts, (string)$index, $urlSuffix);
            }

            $finalPaths[$rewrite['store_id'] . '|' . $originalRequestPath] = $rewrite['request_path'];
            $result[] = $rewrite;
        }

        // Magento points a preserved redirect at the path it generated; follow it to where that path was
        // actually saved, or the redirect lands on a URL that no longer exists (e.g. after a dedup index)
        foreach ($preserved as $rewrite) {
            $generatedTarget = $rewrite['store_id'] . '|' . $rewrite['target_path'];
            if ($rewrite['redirect_type'] && isset($finalPaths[$generatedTarget])) {
                $rewrite['target_path'] = $finalPaths[$generatedTarget];
            }
            $result[] = $rewrite;
        }

        return $result;
    }

    /**
     * Check if Url Rewrite with the same request path exists
     *
     * @param array $rewrite
     * @return string
     */
    protected function _urlRewriteExists(array $rewrite): string|false
    {
        $select = $this->_getResourceConnection()->getConnection()->select()
            ->from($this->_getMainTableName(), ['url_rewrite_id'])
            ->where('entity_type = ?', $rewrite['entity_type'])
            ->where('request_path = ?', $rewrite['request_path'])
            ->where('store_id = ?', $rewrite['store_id'])
            ->where('entity_id != ?', $rewrite['entity_id']);
        return $this->_getResourceConnection()->getConnection()->fetchOne($select);
    }

    /**
     * Check if any Url Rewrite already exists for this entity/store, regardless of request_path
     * (used by --skip-existing, see #50)
     *
     * @param int $entityId
     * @param int $storeId
     * @return bool
     */
    protected function _urlRewriteExistsForEntity(int $entityId, int $storeId): bool
    {
        $select = $this->_getResourceConnection()->getConnection()->select()
            ->from($this->_getMainTableName(), ['url_rewrite_id'])
            ->where('entity_type = ?', $this->entityType)
            ->where('entity_id = ?', $entityId)
            ->where('store_id = ?', $storeId);
        return (bool)$this->_getResourceConnection()->getConnection()->fetchOne($select);
    }

    /**
     * Merge Url Rewrite parts into one string
     *
     * @param array $pathParts
     * @param string $index
     * @param string $urlSuffix
     * @return string
     */
    protected function _mergePartsIntoRewriteRequest(array $pathParts, string $index = '', string $urlSuffix = ''): string
    {
        return (!empty($pathParts['dirname']) ? $pathParts['dirname'] . '/' : '') . $pathParts['filename']
            . (!empty($index) ? '-' . $index : '')
            . (!empty($pathParts['extension']) ? '.' . $pathParts['extension'] : '')
            . ($urlSuffix ?: '');
    }

    /**
     * Get root category I'd of specific store
     *
     * @param int $storeId
     * @return int|null
     */
    protected function _getStoreRootCategoryId(int $storeId): ?int
    {
        if (empty($this->storeRootCategoryId[$storeId])) {
            $value = null;
            try {
                $store = $this->helper->getStoreManager()->getStore($storeId);
                if ($store) {
                    $value = $store->getRootCategoryId();
                }
            } catch (\Exception $e) {
            }

            $this->storeRootCategoryId[$storeId] = $value;
        }

        return $this->storeRootCategoryId[$storeId];
    }
}
