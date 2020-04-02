<?php
/**
 * Regenerate.php
 *
 * @package OlegKoval_RegenerateUrlRewrites
 * @author Oleg Koval <contact@olegkoval.com>
 * @copyright 2017-2067 Oleg Koval
 * @license OSL-3.0, AFL-3.0
 */

namespace OlegKoval\RegenerateUrlRewrites\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Regenerate extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Regenerate constructor.
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager
    )
    {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->scopeConfig = $context->getScopeConfig();
    }

    /**
     * Return array with "support me" text
     * @return array
     */
    public function getSupportMeText()
    {
        return [
            'Please, support me on:',
            'https://www.patreon.com/olegkoval',
            'https://api.fondy.eu/s/aeOD4YCieqKE7U'
        ];
    }

    /**
     * Get store manager
     * @return StoreManagerInterface
     */
    public function getStoreManager()
    {
        return $this->storeManager;
    }

    /**
     * Get config value of "Use Categories Path for Product URLs" config option
     * @param  mixed $storeId
     * @return boolean
     */
    public function useCategoriesPathForProductUrls($storeId = null)
    {
        return (bool) $this->scopeConfig->getValue(
            'catalog/seo/product_use_categories',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );
    }

    /**
     * Sanitize product URL rewrites
     * @param  array $productUrlRewrites
     * @return array
     */
    public function sanitizeProductUrlRewrites($productUrlRewrites)
    {
        $paths = [];
        foreach ($productUrlRewrites as $key => $urlRewrite) {
            $path = $this->_clearRequestPath($urlRewrite->getRequestPath());
            if (!in_array($path, $paths)) {
                $productUrlRewrites[$key]->setRequestPath($path);
                $paths[] = $path;
            } else {
                unset($productUrlRewrites[$key]);
            }
        }

        return $productUrlRewrites;
    }

    /**
     * Clear request path
     * @param  string $requestPath
     * @return string
     */
    protected function _clearRequestPath($requestPath)
    {
        return str_replace(['//', './'], ['/', '/'], ltrim(ltrim($requestPath, '/'), '.'));
    }
}