<?php
/**
 * @category IMI
 * @package IMI_ForwardToConfigurable
 */

namespace IMI\ForwardToConfigurable\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;

class ForwardToConfigurable implements ObserverInterface
{
    const ENABLED_CONFIG_PATH = 'imi_forward_to_configurable/general/enable';

    /**
     * @var \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable
     */
    protected $configurableProductResourceModelProductTypeConfigurable;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $catalogProductFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    protected $dataObjectFactory;

    /**
     * @var \IMI\ForwardToConfigurable\Helper\Data
     */
    protected $forwardHelper;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Framework\App\ResponseInterface
     */
    protected $response;

    /** @var \Magento\Catalog\Helper\Product */
    protected $productHelper;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $configurableProductResourceModelProductTypeConfigurable,
        \Magento\Catalog\Model\ProductFactory $catalogProductFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        \IMI\ForwardToConfigurable\Helper\Data $forwardHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\App\ResponseInterface $response,
        \Magento\Catalog\Helper\Product $productHelper,
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
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(Observer $observer)
    {

        if (!$this->isEnabled()) {
            return;
        }

        $productId = $observer->getEvent()->getProduct()->getId();
        $parentIds = $this->configurableProductResourceModelProductTypeConfigurable->getParentIdsByChild($productId);;
        if (empty($parentIds)) {
            return;
        }

        while (count($parentIds) > 0) {
            $parentId = array_shift($parentIds);

            $parentProduct = $this->catalogProductFactory->create()
                ->setStoreId($this->storeManager->getStore()->getId())
                ->load($parentId);
            if (!$parentProduct->getId()) {
                throw new \Exception(sprintf('Can not load parent product with ID %d', $parentId));
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
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
     * @throws \Magento\Framework\Exception\NoSuchEntityException
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
