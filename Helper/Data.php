<?php
/**
 * @category IMI
 * @package IMI_ForwardToConfigurable
 */

namespace IMI\ForwardToConfigurable\Helper;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
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
     * @param Product $parentProduct
     * @param Product $currentProduct
     * @return array array( configoptionid -> value )
     */
    public function generateConfigData(Product $parentProduct, Product $currentProduct)
    {
        /* @var $typeInstance Configurable */
        $typeInstance = $parentProduct->getTypeInstance();
        if (!$typeInstance instanceof Configurable) {
            return []; // not a configurable product
        }
        $configData = [];
        $attributes = $typeInstance->getUsedProductAttributes($parentProduct);

        foreach ($attributes as $code => $data) {
            $configData[$code] = $currentProduct->getData($data->getAttributeCode());
        }

        return $configData;
    }

}