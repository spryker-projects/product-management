<?php
/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\ProductManagement\Communication\Table;

use Orm\Zed\Availability\Persistence\Map\SpyAvailabilityTableMap;
use Orm\Zed\Product\Persistence\Map\SpyProductLocalizedAttributesTableMap;
use Orm\Zed\Product\Persistence\Map\SpyProductTableMap;
use Orm\Zed\Product\Persistence\SpyProduct;
use Orm\Zed\ProductBundle\Persistence\Map\SpyProductBundleTableMap;
use Orm\Zed\Stock\Persistence\Map\SpyStockProductTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Zed\Gui\Communication\Table\AbstractTable;
use Spryker\Zed\Gui\Communication\Table\TableConfiguration;
use Spryker\Zed\Product\Persistence\ProductQueryContainerInterface;
use Spryker\Zed\ProductManagement\Dependency\Facade\ProductManagementToAvailabilityInterface;
use Spryker\Zed\ProductManagement\Dependency\Facade\ProductManagementToMoneyInterface;
use Spryker\Zed\ProductManagement\Dependency\Facade\ProductManagementToPriceInterface;
use Spryker\Zed\ProductManagement\Dependency\Service\ProductManagementToUtilEncodingInterface;

class BundledProductTable extends AbstractTable
{
    const COL_SELECT = 'select';
    const COL_PRICE = 'price';
    const COL_AVAILABILITY = 'availability';
    const COL_ID_PRODUCT_CONCRETE = 'id_product_concrete';

    /**
     * @var ProductQueryContainerInterface
     */
    protected $productQueryContainer;

    /**
     * @var \Spryker\Zed\ProductManagement\Dependency\Service\ProductManagementToUtilEncodingInterface
     */
    protected $utilEncodingService;

    /**
     * @var \Spryker\Zed\ProductManagement\Dependency\Facade\ProductManagementToPriceInterface
     */
    protected $priceFacade;

    /**
     * @var \Spryker\Zed\ProductManagement\Dependency\Facade\ProductManagementToMoneyInterface
     */
    protected $moneyFacade;

    /**
     * @var \Spryker\Zed\ProductManagement\Dependency\Facade\ProductManagementToAvailabilityInterface
     */
    protected $availabilityFacade;

    /**
     * @var int
     */
    protected $idProductConcrete;

    /**
     * @param ProductQueryContainerInterface $productQueryContainer
     * @param \Spryker\Zed\ProductManagement\Dependency\Service\ProductManagementToUtilEncodingInterface $utilEncodingService
     * @param \Spryker\Zed\ProductManagement\Dependency\Facade\ProductManagementToPriceInterface $priceFacade
     * @param \Spryker\Zed\ProductManagement\Dependency\Facade\ProductManagementToMoneyInterface $moneyFacade
     * @param \Spryker\Zed\ProductManagement\Dependency\Facade\ProductManagementToAvailabilityInterface $availabilityFacade
     * @param int $idProductConcrete
     */
    public function __construct(
        ProductQueryContainerInterface $productQueryContainer,
        ProductManagementToUtilEncodingInterface $utilEncodingService,
        ProductManagementToPriceInterface $priceFacade,
        ProductManagementToMoneyInterface $moneyFacade,
        ProductManagementToAvailabilityInterface $availabilityFacade,
        $idProductConcrete = null
    ) {
        $this->setTableIdentifier('bundled-product-table');
        $this->productQueryContainer = $productQueryContainer;
        $this->utilEncodingService = $utilEncodingService;
        $this->priceFacade = $priceFacade;
        $this->moneyFacade = $moneyFacade;
        $this->availabilityFacade = $availabilityFacade;
        $this->idProductConcrete = $idProductConcrete;
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     *
     * @return \Spryker\Zed\Gui\Communication\Table\TableConfiguration
     */
    protected function configure(TableConfiguration $config)
    {
        $config->setUrl(sprintf('bundledProductTable?id-product-concrete=%d', $this->idProductConcrete));

        $config->setHeader([
            static::COL_SELECT => 'Select',
            static::COL_ID_PRODUCT_CONCRETE => 'id product',
            SpyProductLocalizedAttributesTableMap::COL_NAME => 'Product name',
            SpyProductTableMap::COL_SKU => 'SKU',
            static::COL_PRICE => 'Price',
            SpyStockProductTableMap::COL_QUANTITY => 'Stock',
            static::COL_AVAILABILITY => 'Availability'
        ]);

        $config->setRawColumns([
            static::COL_SELECT,
            static::COL_PRICE,
            static::COL_AVAILABILITY,
        ]);

        $config->setSearchable([
            SpyProductLocalizedAttributesTableMap::COL_NAME,
            SpyProductTableMap::COL_SKU
        ]);

        $config->setSortable([
            SpyProductLocalizedAttributesTableMap::COL_NAME,
            SpyProductTableMap::COL_SKU,
            SpyStockProductTableMap::COL_QUANTITY
        ]);

        return $config;
    }

    /**
     * @param \Spryker\Zed\Gui\Communication\Table\TableConfiguration $config
     *
     * @return array
     */
    protected function prepareData(TableConfiguration $config)
    {
        $query = $this
            ->productQueryContainer
            ->queryProduct()
            ->leftJoinSpyProductBundleRelatedByFkProduct()
            ->joinSpyProductLocalizedAttributes()
            ->joinStockProduct()
            ->withColumn(SpyProductLocalizedAttributesTableMap::COL_NAME, 'Name')
            ->withColumn(SpyStockProductTableMap::COL_QUANTITY, 'stockQuantity')
            ->where(SpyProductLocalizedAttributesTableMap::COL_FK_LOCALE .' = ?', 66)
            ->add(SpyProductBundleTableMap::COL_ID_PRODUCT_BUNDLE, null, CRITERIA::ISNULL);

        $queryResults = $this->runQuery($query, $config, true);

        $productAbstractCollection = [];
        foreach ($queryResults as $item) {

            $availability = $this->availabilityFacade->calculateStockForProduct($item->getSku());

            $productAbstractCollection[] = [
                static::COL_SELECT  => $this->addCheckBox($item),
                static::COL_ID_PRODUCT_CONCRETE =>$item->getIdProduct(),
                SpyProductLocalizedAttributesTableMap::COL_NAME => $item->getName(),
                SpyProductTableMap::COL_SKU => $item->getSku(),
                static::COL_PRICE => $this->getFormatedPrice($item->getSku()),
                SpyStockProductTableMap::COL_QUANTITY => $item->getStockQuantity(),
                static::COL_AVAILABILITY => $availability
            ];
        }

        return $productAbstractCollection;
    }

    /**
     * @param string $sku
     *
     * @return string
     */
    protected function getFormatedPrice($sku)
    {
        $priceInCents = $this->priceFacade->getPriceBySku($sku);

        $moneyTransfer = $this->moneyFacade->fromInteger($priceInCents);

        return $this->moneyFacade->formatWithSymbol($moneyTransfer);
    }

    /**
     * @param SpyProduct $productConcreteEntity
     *
     * @return string
     */
    protected function addCheckBox(SpyProduct $productConcreteEntity)
    {
        $checked = '';
        if ($this->idProductConcrete) {
            $criteria = new Criteria();
            $criteria->add(SpyProductBundleTableMap::COL_FK_PRODUCT, $this->idProductConcrete);

            if ($productConcreteEntity->getSpyProductBundlesRelatedByFkBundledProduct($criteria)->count() > 0) {
                $checked = 'checked="checked"';
            }
        }

        return sprintf(
            "<input id='product_assign_checkbox_%d' class='product_assign_checkbox' type='checkbox' data-info='%s' %s >",
            $productConcreteEntity->getIdProduct(),
            $this->utilEncodingService->encodeJson($productConcreteEntity->toArray()),
            $checked
        );
    }
}
