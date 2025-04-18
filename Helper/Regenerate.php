<?php
/**
 * Regenerate.php
 *
 * @package OlegKoval_RegenerateUrlRewrites
 * @author Oleg Koval <olegkoval.ca@gmail.com>
 * @copyright 2017-2067 Oleg Koval
 * @license OSL-3.0, AFL-3.0
 */

namespace OlegKoval\RegenerateUrlRewrites\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Regenerate extends AbstractHelper
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
        Context               $context,
        StoreManagerInterface $storeManager
    )
    {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->scopeConfig = $context->getScopeConfig();
    }

    /**
     * Return array with "support me" text
     *
     * @return array
     */
    public function getSupportMeText(): array
    {
        return [
            'Please, support me on:',
            'PayPal: olegkoval.ca@gmail.com',
            'https://www.paypal.com/donate/?hosted_button_id=995MLRKBNY9QQ',
            'https://www.patreon.com/olegkoval',
            'https://ko-fi.com/olegkoval77',
        ];
    }

    /**
     * @return string
     */
    public function getPurchaseProVersionMsg(): string
    {
        return __('To use this option you should purchase a Pro version.')->render();
    }

    /**
     * @return bool
     */
    public function isRegisteredProVersion(): bool
    {
        return true;
    }

    /**
     * Get store manager
     *
     * @return StoreManagerInterface
     */
    public function getStoreManager(): StoreManagerInterface
    {
        return $this->storeManager;
    }

    /**
     * Get config value of "Use Categories Path for Product URLs" config option
     *
     * @param int|null $storeId
     * @return boolean
     */
    public function useCategoriesPathForProductUrls(int $storeId = null): bool
    {
        return (bool)$this->scopeConfig->getValue(
            'catalog/seo/product_use_categories',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );
    }

    /**
     * Sanitize product URL rewrites
     *
     * @param array $productUrlRewrites
     * @return array
     */
    public function sanitizeProductUrlRewrites(array $productUrlRewrites): array
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
     * @param string $requestPath
     * @return string
     */
    protected function _clearRequestPath(string $requestPath): string
    {
        return str_replace(['//', './'], ['/', '/'], ltrim(ltrim($requestPath, '/'), '.'));
    }
}
