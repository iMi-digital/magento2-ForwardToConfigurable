<?php
/**
 * @category IMI
 * @package IMI_ForwardToConfigurable
 */

namespace IMI\ForwardToConfigurable\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

/**
 * Class Data
 * @package IMI\ForwardToConfigurable\Helper
 */
class Data extends AbstractHelper
{
    /**
     * Generates config array to reflect the simple product's ($currentProduct)
     * configuration in its parent configurable product
     *
     * @param \Magento\Catalog\Model\Product $parentProduct
     * @param \Magento\Catalog\Model\Product $currentProduct
     * @return array array( configoptionid -> value )
     */
    public function generateConfigData(\Magento\Catalog\Model\Product $parentProduct, \Magento\Catalog\Model\Product $currentProduct)
    {
        /* @var $typeInstance \Magento\ConfigurableProduct\Model\Product\Type\Configurable */
        $typeInstance = $parentProduct->getTypeInstance();
        if (!$typeInstance instanceof \Magento\ConfigurableProduct\Model\Product\Type\Configurable) {
            return; // not a configurable product
        }
        $configData = array();
        $attributes = $typeInstance->getUsedProductAttributes($parentProduct);

        foreach ($attributes as $code => $data) {
            $configData[$code] = $currentProduct->getData($data->getAttributeCode());
        }

        return $configData;
    }

}