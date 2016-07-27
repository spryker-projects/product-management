<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\ProductManagement\Communication\Controller;

use Generated\Shared\Transfer\LocaleTransfer;
use Generated\Shared\Transfer\LocalizedAttributesTransfer;
use Generated\Shared\Transfer\ProductAbstractTransfer;
use Generated\Shared\Transfer\ProductManagementAttributeTransfer;
use Generated\Shared\Transfer\ProductManagementAttributeValueTransfer;
use Generated\Shared\Transfer\ProductManagementAttributeValueTranslationTransfer;
use Generated\Shared\Transfer\ZedProductConcreteTransfer;
use Spryker\Shared\ProductManagement\ProductManagementConstants;
use Spryker\Zed\Application\Communication\Controller\AbstractController;
use Spryker\Zed\Category\Business\Exception\CategoryUrlExistsException;
use Spryker\Zed\ProductManagement\Business\Attribute\AttributeProcessor;
use Spryker\Zed\ProductManagement\Business\Product\MatrixGenerator;
use Spryker\Zed\ProductManagement\Communication\Form\ProductFormAdd;
use Spryker\Zed\ProductManagement\Communication\Form\ProductFormAttributeAbstract;
use Spryker\Zed\ProductManagement\Communication\Form\ProductFormPrice;
use Spryker\Zed\ProductManagement\Communication\Form\ProductFormSeo;
use Symfony\Component\HttpFoundation\Request;

/**
 * @method \Spryker\Zed\ProductManagement\Business\ProductManagementFacade getFacade()
 * @method \Spryker\Zed\ProductManagement\Communication\ProductManagementCommunicationFactory getFactory()
 * @method \Spryker\Zed\ProductManagement\Persistence\ProductManagementQueryContainer getQueryContainer()
 */
class AddController extends AbstractController
{

    const PARAM_ID_PRODUCT_ABSTRACT = 'id-product-abstract';

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function indexAction(Request $request)
    {
        $dataProvider = $this->getFactory()->createProductFormAddDataProvider();
        $form = $this
            ->getFactory()
            ->createProductFormAdd(
                $dataProvider->getData(),
                $dataProvider->getOptions()
            )
            ->handleRequest($request);

        $attributeCollection = $this->normalizeAttributeArray(
            $this->getFactory()->getProductAttributeCollection()
        );

        if ($form->isValid()) {
            try {
                $data = $form->getData();
                //$attributeTransferCollection = $this->convertAttributeTransferFromData($data, $attributeCollection);
                $attributeValues = $this->convertAttributeArrayFromData($data);

                $productAbstractTransfer = $this->buildProductAbstractTransferFromData($data, $attributeValues);
                //$matrixGenerator = new MatrixGenerator();
                //$concreteProductCollection = $matrixGenerator->generate($productAbstractTransfer, $attributeCollection, $attributeValues);
                //ddd($productAbstractTransfer->toArray(), $attributeValues, $attributeCollection, $form->getData(), $_POST);

                $idProductAbstract = $this->getFactory()
                    ->getProductManagementFacade()
                    ->addProduct($productAbstractTransfer, []);

                $this->addSuccessMessage('The product was added successfully.');

                return $this->redirectResponse(sprintf(
                    '/product-management/edit?%s=%d',
                    self::PARAM_ID_PRODUCT_ABSTRACT,
                    $idProductAbstract
                ));
            } catch (CategoryUrlExistsException $exception) {
                $this->addErrorMessage($exception->getMessage());
            }
        }

        $localeCollection = ProductFormAdd::getLocaleCollection();
        $attributeLocaleCollection = ProductFormAdd::getLocaleCollection(true);

        return $this->viewResponse([
            'form' => $form->createView(),
            'currentLocale' => $this->getFactory()->getLocaleFacade()->getCurrentLocale()->getLocaleName(),
            'matrix' => [],
            'localeCollection' => $localeCollection,
            'attributeLocaleCollection' => $attributeLocaleCollection
        ]);
    }

    /**
     * @param array $formData
     *
     * @return \Generated\Shared\Transfer\ProductAbstractTransfer
     */
    protected function buildProductAbstractTransferFromData(array $formData, array $attributeValues)
    {
        $localeCollection = ProductFormAdd::getLocaleCollection(false);
        $productAbstractTransfer = $this->createProductAbstractTransfer(
            $formData,
            $attributeValues[ProductManagementConstants::PRODUCT_MANAGEMENT_DEFAULT_LOCALE]
        );

        $localizedData = [];
        foreach ($localeCollection as $code) {
            $formName = ProductFormAdd::getGeneralFormName($code);
            $localizedData[$code] = $formData[$formName];
        }

        foreach ($localeCollection as $code) {
            $formName = ProductFormAdd::getSeoFormName($code);
            $localizedData[$code] = array_merge($localizedData[$code], $formData[$formName]);
        }

        foreach ($localizedData as $code => $data) {
            $localeTransfer = $this->getFactory()->getLocaleFacade()->getLocale($code);
            $localizedAttributesTransfer = $this->createLocalizedAttributesTransfer(
                $data,
                $attributeValues[$code],
                $localeTransfer
            );

            $productAbstractTransfer->addLocalizedAttributes($localizedAttributesTransfer);
        }

        return $productAbstractTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\ProductAbstractTransfer $productAbstractTransfer
     * @param array $formData
     *
     * @return \Generated\Shared\Transfer\ProductConcreteTransfer
     */
    protected function buildProductConcreteTransferFromData(ProductAbstractTransfer $productAbstractTransfer, array $formData)
    {
        $productConcreteTransfer = new ZedProductConcreteTransfer();
        $productConcreteTransfer->setAttributes([]);
        $productConcreteTransfer->setSku($productAbstractTransfer->getSku() . '-' . rand(1, 999));
        $productConcreteTransfer->setIsActive(false);
        $productConcreteTransfer->setAbstractSku($productAbstractTransfer->getSku());
        $productConcreteTransfer->setFkProductAbstract($productAbstractTransfer->getIdProductAbstract());

        $attributeData = $formData[ProductFormAdd::GENERAL];
        foreach ($attributeData as $localeCode => $localizedAttributesData) {
            $localeTransfer = $this->getFactory()->getLocaleFacade()->getLocale($localeCode);

            $localizedAttributesTransfer = $this->createLocalizedAttributesTransfer(
                $localizedAttributesData,
                [],
                $localeTransfer
            );

            $productConcreteTransfer->addLocalizedAttributes($localizedAttributesTransfer);
        }

        return $productConcreteTransfer;
    }

    /**
     * @param array $data
     *
     * @return \Generated\Shared\Transfer\ProductAbstractTransfer
     */
    protected function createProductAbstractTransfer(array $data, array $attributes)
    {
        $attributes = array_filter($attributes);
        $productAbstractTransfer = new ProductAbstractTransfer();

        $productAbstractTransfer->setSku(
            $this->slugify($data[ProductFormAdd::FIELD_SKU])
        );

        $productAbstractTransfer->setAttributes($attributes);

        $productAbstractTransfer->setTaxSetId($data[ProductFormAdd::PRICE_AND_STOCK][ProductFormPrice::FIELD_TAX_RATE]);

        return $productAbstractTransfer;
    }

    /**
     * @param array $data
     * @param array $abstractLocalizedAttributes
     * @param \Generated\Shared\Transfer\LocaleTransfer $localeTransfer
     *
     * @return \Generated\Shared\Transfer\LocalizedAttributesTransfer
     */
    protected function createLocalizedAttributesTransfer(array $data, array $abstractLocalizedAttributes, LocaleTransfer $localeTransfer)
    {
        $abstractLocalizedAttributes = array_filter($abstractLocalizedAttributes);
        $localizedAttributesTransfer = new LocalizedAttributesTransfer();
        $localizedAttributesTransfer->setLocale($localeTransfer);
        $localizedAttributesTransfer->setName($data[ProductFormAdd::FIELD_NAME]);
        $localizedAttributesTransfer->setAttributes($abstractLocalizedAttributes);
        $localizedAttributesTransfer->setMetaTitle($data[ProductFormSeo::FIELD_META_TITLE]);
        $localizedAttributesTransfer->setMetaKeywords($data[ProductFormSeo::FIELD_META_KEYWORDS]);
        $localizedAttributesTransfer->setMetaDescription($data[ProductFormSeo::FIELD_META_DESCRIPTION]);

        return $localizedAttributesTransfer;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public function slugify($value)
    {
        if (function_exists('iconv')) {
            $value = iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        }

        $value = preg_replace("/[^a-zA-Z0-9 -]/", "", trim($value));
        $value = strtolower($value);
        $value = str_replace(' ', '-', $value);

        return $value;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function convertAttributeArrayFromData(array $data)
    {
        $attributes = [];
        $localeCollection = ProductFormAdd::getLocaleCollection(true);

        foreach ($localeCollection as $code) {
            $formName = ProductFormAdd::getAbstractAttributeFormName($code);
            foreach ($data[$formName] as $type => $values) {
                $attributes[$code][$type] = $values['value'];
            }
        }

        return $attributes;
    }

    /**
     * @param array $formData
     * @param array $attributeCollection
     *
     * @return \Generated\Shared\Transfer\ProductManagementAttributeTransfer[]
     */
    protected function convertAttributeTransferFromData(array $formData, array $attributeCollection)
    {
        $attributeValues = $this->convertAttributeArrayFromData($formData);

        $attributeProcessor = new AttributeProcessor();
        $localeCollection = ProductFormAdd::getLocaleCollection(true);

        foreach ($formData[ProductManagementConstants::PRODUCT_MANAGEMENT_DEFAULT_LOCALE] as $type => $itemData) {
            /* @var  ProductManagementAttributeTransfer $attributeTransfer */
            $attributeTransfer = $attributeCollection[$type];

            $nameCheckbox = $itemData[ProductFormAttributeAbstract::FIELD_NAME];
            $value = $itemData[ProductFormAttributeAbstract::FIELD_VALUE];
            $idValue = (int)$itemData[ProductFormAttributeAbstract::FIELD_VALUE_HIDDEN_ID];

            if (!$nameCheckbox) {
                continue;
            }

            $valueTransfer = (new ProductManagementAttributeValueTransfer())
                ->setFkProductManagementAttribute($attributeTransfer->getIdProductManagementAttribute())
                ->setIdProductManagementAttributeValue($idValue)
                ->setValue($value);

            $attributeTransfer->addValue($valueTransfer);
        }

        unset($formData[ProductManagementConstants::PRODUCT_MANAGEMENT_DEFAULT_LOCALE]);

        foreach ($localeCollection as $code) {
            $localeTransfer = $this->getFactory()->getLocaleFacade()->getLocale($code);
            $formName = ProductFormAdd::getAbstractAttributeFormName($code);
            foreach ($formData[$formName] as $type => $itemData) {
                /* @var  ProductManagementAttributeTransfer $attributeTransfer */
                $attributeTransfer = $attributeCollection[$type];

                $nameCheckbox = $itemData[ProductFormAttributeAbstract::FIELD_NAME];
                $value = $itemData[ProductFormAttributeAbstract::FIELD_VALUE];
                $idValue = (int)$itemData[ProductFormAttributeAbstract::FIELD_VALUE_HIDDEN_ID];

                if (!$nameCheckbox) {
                    continue;
                }

                $localizedValue = (new ProductManagementAttributeValueTranslationTransfer())
                    ->setIdProductManagementAttributeValue($attributeTransfer->getIdProductManagementAttribute())
                    ->setFkLocale($localeTransfer->getIdLocale())
                    ->setTranslation($value);

                $attributeTransfer->addLoca($valueTransfer);
            }
        }

        return $attributeCollection;
    }

    /**
     * @param array $keys
     * @param array $attributes
     *
     * @return array
     */
    protected function getAttributeValues(array $keys, array $attributes)
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] =  $attributes[$key];
        }

        return $values;
    }

    /**
     * @param array $attributeCollection
     *
     * @return \Generated\Shared\Transfer\ProductManagementAttributeTransfer[]
     */
    protected function normalizeAttributeArray(array $attributeCollection)
    {
        $attributeArray = [];
        foreach ($attributeCollection as $attributeTransfer) {
            $attributeArray[$attributeTransfer->getKey()] = $attributeTransfer;
        }

        return $attributeArray;
    }

    /**
     * @param \Generated\Shared\Transfer\ProductManagementAttributeMetadataTransfer[] $metadataCollection
     * @param \Generated\Shared\Transfer\ProductManagementAttributeTransfer[] $attributeCollection
     *
     * @return array
     */
    protected function getLocalizedAttributeMetadataNames(array $metadataCollection, array $attributeCollection)
    {
        $currentLocale = (int)$this->getFactory()
            ->getLocaleFacade()
            ->getCurrentLocale()
            ->getIdLocale();

        $result = [];
        foreach ($metadataCollection as $type => $transfer) {
            $result[$type] = $type;
            if (!isset($attributeCollection[$type])) {
                continue;
            }

            $attributeTransfer = $attributeCollection[$type];
            foreach ($attributeTransfer->getLocalizedAttributes() as $localizedAttribute) {
                if ((int)$localizedAttribute->getFkLocale() === $currentLocale) {
                    $result[$type] = $localizedAttribute->getName();
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * @param array $concreteProductCollection
     *
     * @return array
     */
    protected function someToView($idProductAbstract, array $concreteProductCollection)
    {
        $r = [];
        foreach ($concreteProductCollection as $t) {
            $c = $t->toArray(true);
            ;
            $c['attributes'] = $this->getFacade()->getProductAttributesByAbstractProductId($idProductAbstract);
            $r[] = $c;
        }

        return $r;
    }

}
