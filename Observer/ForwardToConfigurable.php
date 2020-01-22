<?php
/**
 * @category IMI
 * @package IMI_ForwardToConfigurable
 */

namespace IMI\ForwardToConfigurable\Observer;

use Exception;
use IMI\ForwardToConfigurable\Helper\Data;
use Magento\Catalog\Helper\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ForwardToConfigurable implements ObserverInterface
{
    const ENABLED_CONFIG_PATH = 'imi_forward_to_configurable/general/enable';

    /**
     * @var Configurable
     */
    protected $configurableProductResourceModelProductTypeConfigurable;

    /**
     * @var ProductFactory
     */
    protected $catalogProductFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var DataObjectFactory
     */
    protected $dataObjectFactory;

    /**
     * @var Data
     */
    protected $forwardHelper;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /** @var Product */
    protected $productHelper;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        Configurable $configurableProductResourceModelProductTypeConfigurable,
        ProductFactory $catalogProductFactory,
        StoreManagerInterface $storeManager,
        DataObjectFactory $dataObjectFactory,
        Data $forwardHelper,
        RequestInterface $request,
        ResponseInterface $response,
        Product $productHelper,
        ScopeConfigInterface $scopeConfig

    ) {
        $this->configurableProductResourceModelProductTypeConfigurable = $configurableProductResourceModelProductTypeConfigurable;
        $this->catalogProductFactory = $catalogProductFactory;
        $this->storeManager = $storeManager;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->forwardHelper = $forwardHelper;
        $this->request = $request;
        $this->response = $response;
        $this->productHelper = $productHelper;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function execute(Observer $observer)
    {

        if (!$this->isEnabled()) {
            return;
        }

        $productId = $observer->getEvent()->getProduct()->getId();
        $parentIds = $this->configurableProductResourceModelProductTypeConfigurable->getParentIdsByChild($productId);
        if (empty($parentIds)) {
            return;
        }

        while (count($parentIds) > 0) {
            $parentId = array_shift($parentIds);

            $parentProduct = $this->catalogProductFactory->create()
                ->setStoreId($this->storeManager->getStore()->getId())
                ->load($parentId);
            if (!$parentProduct->getId()) {
                throw new Exception(sprintf('Can not load parent product with ID %d', $parentId));
            }

            if ($this->guessCanShow($parentProduct)) {
                break;
            }
            // try to find other products if one parent product is not visible -> loop
        }
        if (!$this->guessCanShow($parentProduct)) {
            return;
        }

        /* @var $currentProduct Mage_Catalog_Model_Product */
        $currentProduct = $this->catalogProductFactory->create();
        $currentProduct->load($productId);

        $buyRequest = $this->dataObjectFactory->create();
        $buyRequest->setSuperAttribute($this->forwardHelper->generateConfigData($parentProduct, $currentProduct)); // example format: array(525 => "99"));
        $redirectUrl = $parentProduct->getProductUrl();
        $i = 0;
        $requestParams = $this->request->getParams();
        foreach ($requestParams as $param => $paramValue) {
            if ($i == 0) {
                $redirectUrl .= '?';
            } else {
                $redirectUrl .= '&';
            }
            $redirectUrl .= $param . '=' . $paramValue;
            $i++;
        }
        $i = 0;
        foreach ($buyRequest->getData('super_attribute') as $attributeId => $attributeValue) {
            if ($i == 0) {
                $redirectUrl .= '#';
            } else {
                $redirectUrl .= '&';
            }
            $redirectUrl .= $attributeId . '=' . $attributeValue;
            $i++;
        }
        $this->response->setRedirect($redirectUrl)->sendResponse();

        return;
    }

    /**
     * Roughly reproducing the logic of
     *
     * @see \Magento\Catalog\Controller\Product\View::execute
     *
     * but we can't do this completely without calling the controller.
     * So for now we catch the currently relevant cases.
     *
     * Improvements welcome :-)
     *
     * @param $product
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    protected function guessCanShow($product)
    {
        if(!$this->productHelper->canShow($product)) {
            return false;
        }

        if (!in_array($this->storeManager->getStore()->getWebsiteId(), $product->getWebsiteIds())) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    private function isEnabled(): bool
    {
        $enabled = (bool)$this->scopeConfig->getValue(
            self::ENABLED_CONFIG_PATH,
            ScopeInterface::SCOPE_STORES,
            $this->storeManager->getStore()->getId()
        );

        return $enabled;
    }
}
