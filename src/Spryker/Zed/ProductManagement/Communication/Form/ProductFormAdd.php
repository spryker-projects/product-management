<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\ProductManagement\Communication\Form;

use Generated\Shared\Transfer\LocaleTransfer;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Shared\ProductManagement\ProductManagementConstants;
use Spryker\Zed\Gui\Communication\Form\Validator\Constraints\SkuRegex;
use Spryker\Zed\ProductManagement\Communication\Form\DataProvider\AbstractProductFormDataProvider;
use Spryker\Zed\ProductManagement\Communication\Form\DataProvider\LocaleProvider;
use Spryker\Zed\ProductManagement\Communication\Form\Product\AttributeAbstractForm;
use Spryker\Zed\ProductManagement\Communication\Form\Product\AttributeVariantForm;
use Spryker\Zed\ProductManagement\Communication\Form\Product\GeneralForm;
use Spryker\Zed\ProductManagement\Communication\Form\Product\ImageCollectionForm;
use Spryker\Zed\ProductManagement\Communication\Form\Product\ImageForm;
use Spryker\Zed\ProductManagement\Communication\Form\Product\PriceForm;
use Spryker\Zed\ProductManagement\Communication\Form\Product\SeoForm;
use Spryker\Zed\ProductManagement\Persistence\ProductManagementQueryContainerInterface;
use Spryker\Zed\Product\Persistence\ProductQueryContainerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ProductFormAdd extends AbstractType
{

    const FIELD_SKU = 'sku';
    const FIELD_ID_PRODUCT_ABSTRACT = 'id_product_abstract';

    const FORM_ATTRIBUTE_ABSTRACT = 'attribute_abstract';
    const FORM_ATTRIBUTE_VARIANT = 'attribute_variant';
    const FORM_GENERAL = 'general';
    const FORM_PRICE_AND_TAX = 'price_and_tax';
    const FORM_PRICE_AND_STOCK = 'price_and_stock';
    const FORM_TAX_SET = 'tax_set';
    const FORM_SEO = 'seo';
    const FORM_IMAGE_SET = 'image_set';

    const OPTION_ATTRIBUTE_ABSTRACT = 'option_attribute_abstract';
    const OPTION_ATTRIBUTE_VARIANT = 'option_attribute_variant';
    const OPTION_ID_LOCALE = 'option_id_locale';
    const OPTION_TAX_RATES = 'option_tax_rates';

    const VALIDATION_GROUP_UNIQUE_SKU = 'validation_group_unique_sku';
    const VALIDATION_GROUP_ATTRIBUTE_ABSTRACT = 'validation_group_attribute_abstract';
    const VALIDATION_GROUP_ATTRIBUTE_VARIANT = 'validation_group_attribute_variant';
    const VALIDATION_GROUP_GENERAL = 'validation_group_general';
    const VALIDATION_GROUP_PRICE_AND_TAX = 'validation_group_price_and_tax';
    const VALIDATION_GROUP_PRICE_AND_STOCK = 'validation_group_price_and_stock';
    const VALIDATION_GROUP_SEO = 'validation_group_seo';
    const VALIDATION_GROUP_IMAGE_SET = 'validation_group_image';

    /**
     * @var \Spryker\Zed\ProductManagement\Communication\Form\DataProvider\LocaleProvider
     */
    protected $localeProvider;

    /**
     * @var \Spryker\Zed\Product\Persistence\ProductQueryContainerInterface
     */
    protected $productQueryContainer;

    /**
     * @var \Spryker\Zed\ProductManagement\Persistence\ProductManagementQueryContainerInterface
     */
    protected $productManagementQueryContainer;

    /**
     * @param \Spryker\Zed\ProductManagement\Communication\Form\DataProvider\LocaleProvider $localeProvider
     * @param \Spryker\Zed\Product\Persistence\ProductQueryContainerInterface $productQueryContainer
     * @param \Spryker\Zed\ProductManagement\Persistence\ProductManagementQueryContainerInterface $productManagementQueryContainer
     */
    public function __construct(
        LocaleProvider $localeProvider,
        ProductQueryContainerInterface $productQueryContainer,
        ProductManagementQueryContainerInterface $productManagementQueryContainer
    ) {

        $this->localeProvider = $localeProvider;
        $this->productQueryContainer = $productQueryContainer;
        $this->productManagementQueryContainer = $productManagementQueryContainer;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'ProductFormAdd';
    }

    /**
     * @param \Symfony\Component\OptionsResolver\OptionsResolverInterface $resolver
     *
     * @return void
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        parent::setDefaultOptions($resolver);

        $resolver->setRequired(self::OPTION_ID_LOCALE);
        $resolver->setRequired(self::OPTION_ATTRIBUTE_ABSTRACT);
        $resolver->setRequired(self::OPTION_ATTRIBUTE_VARIANT);
        $resolver->setRequired(self::OPTION_TAX_RATES);

        $validationGroups = $this->getValidationGroups();

        $resolver->setDefaults([
            'cascade_validation' => true,
            'required' => false,
            'validation_groups' => function (FormInterface $form) use ($validationGroups) {
                return $validationGroups;
            },
            'compound' => true,
        ]);
    }

    /**
     * @return array
     */
    protected function getValidationGroups()
    {
        return [
            Constraint::DEFAULT_GROUP,
            self::VALIDATION_GROUP_UNIQUE_SKU,
            self::VALIDATION_GROUP_GENERAL,
            self::VALIDATION_GROUP_PRICE_AND_TAX,
            self::VALIDATION_GROUP_PRICE_AND_STOCK,
            self::VALIDATION_GROUP_ATTRIBUTE_ABSTRACT,
            self::VALIDATION_GROUP_ATTRIBUTE_VARIANT,
            self::VALIDATION_GROUP_SEO,
            self::VALIDATION_GROUP_IMAGE_SET,
        ];
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array $options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $this
            ->addSkuField($builder)
            ->addProductAbstractIdHiddenField($builder)
            ->addGeneralLocalizedForms($builder)
            ->addAttributeAbstractForms($builder, $options[self::OPTION_ATTRIBUTE_ABSTRACT])
            ->addAttributeVariantForm($builder, $options[self::OPTION_ATTRIBUTE_VARIANT])
            ->addPriceForm($builder, $options[self::OPTION_TAX_RATES])
            ->addSeoLocalizedForms($builder)
            ->addImageLocalizedForms($builder);
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     *
     * @return $this
     */
    protected function addGeneralLocalizedForms(FormBuilderInterface $builder)
    {
        $localeCollection = $this->localeProvider->getLocaleCollection();
        foreach ($localeCollection as $localeCode) {
            $name = self::getGeneralFormName($localeCode);
            $this->addGeneralForm($builder, $name);
        }

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array $options
     *
     * @return $this
     */
    protected function addSeoLocalizedForms(FormBuilderInterface $builder, array $options = [])
    {
        $localeCollection = $this->localeProvider->getLocaleCollection();
        foreach ($localeCollection as $localeCode) {
            $name = self::getSeoFormName($localeCode);
            $this->addSeoForm($builder, $name, $options);
        }

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array $options
     *
     * @return $this
     */
    protected function addAttributeAbstractForms(FormBuilderInterface $builder, array $options = [])
    {
        $localeCollection = $this->localeProvider->getLocaleCollection();
        foreach ($localeCollection as $localeCode) {
            $name = self::getAbstractAttributeFormName($localeCode);
            $localeTransfer = $this->localeProvider->getLocaleTransfer($localeCode);
            $this->addAttributeAbstractForm($builder, $name, $localeTransfer, $options[$localeCode]);
        }

        $defaultName = ProductFormAdd::getLocalizedPrefixName(
            ProductFormAdd::FORM_ATTRIBUTE_ABSTRACT,
            ProductManagementConstants::PRODUCT_MANAGEMENT_DEFAULT_LOCALE
        );

        $this->addAttributeAbstractForm(
            $builder,
            $defaultName,
            null,
            $options[ProductManagementConstants::PRODUCT_MANAGEMENT_DEFAULT_LOCALE]
        );

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     *
     * @return $this
     */
    protected function addImageLocalizedForms(FormBuilderInterface $builder)
    {
        $localeCollection = $this->localeProvider->getLocaleCollection(true);
        foreach ($localeCollection as $localeCode) {
            $name = self::getImagesFormName($localeCode);
            $this->addImageForm($builder, $name);
        }

        $defaultName = ProductFormAdd::getLocalizedPrefixName(
            ProductFormAdd::FORM_IMAGE_SET,
            ProductManagementConstants::PRODUCT_MANAGEMENT_DEFAULT_LOCALE
        );

        $this->addImageForm($builder, $defaultName);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     *
     * @return $this
     */
    protected function addSkuField(FormBuilderInterface $builder)
    {
        $builder
            ->add(self::FIELD_SKU, 'text', [
                'label' => 'SKU Prefix',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'groups' => [self::VALIDATION_GROUP_UNIQUE_SKU]
                    ]),
                    new SkuRegex([
                        'groups' => [self::VALIDATION_GROUP_UNIQUE_SKU]
                    ]),
                    new Callback([
                        'methods' => [
                            function ($sku, ExecutionContextInterface $context) {
                                $form = $context->getRoot();
                                $idProductAbstract = $form->get(ProductFormAdd::FIELD_ID_PRODUCT_ABSTRACT)->getData();
                                $sku = AbstractProductFormDataProvider::slugify($sku);

                                $skuCount = $this->productQueryContainer
                                    ->queryProduct()
                                    ->filterByFkProductAbstract($idProductAbstract, Criteria::NOT_EQUAL)
                                    ->filterBySku($sku)
                                    ->_or()
                                    ->useSpyProductAbstractQuery()
                                        ->filterBySku($sku)
                                    ->endUse()
                                    ->count();

                                if ($skuCount > 0) {
                                    $context->addViolation(
                                        sprintf('The SKU "%s" is already used', $sku)
                                    );
                                }
                            },
                        ],
                        'groups' => [self::VALIDATION_GROUP_UNIQUE_SKU]
                    ]),
                ],
            ]);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     *
     * @return $this
     */
    protected function addProductAbstractIdHiddenField(FormBuilderInterface $builder)
    {
        $builder
            ->add(self::FIELD_ID_PRODUCT_ABSTRACT, 'hidden', []);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param string $name
     * @param array $options
     *
     * @return $this
     */
    protected function addGeneralForm(FormBuilderInterface $builder, $name, array $options = [])
    {
        $builder
            ->add($name, new GeneralForm($name), [
                'label' => false,
                'constraints' => [new Callback([
                    'methods' => [
                        function ($dataToValidate, ExecutionContextInterface $context) {
                            $selectedAttributes = array_filter(array_values($dataToValidate));
                            if (empty($selectedAttributes) && !array_key_exists($context->getGroup(), GeneralForm::$errorFieldsDisplayed)) {
                                $context->addViolation('Please enter at least Sku and Name of the product in every locale under General', [$context->getGroup()]);
                                GeneralForm::$errorFieldsDisplayed[$context->getGroup()] = true;
                            }
                        },
                    ],
                    'groups' => [self::VALIDATION_GROUP_GENERAL]
                ])]
            ]);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param string $name
     * @param \Generated\Shared\Transfer\LocaleTransfer|null $localeTransfer
     * @param array $options
     *
     * @return $this
     */
    protected function addAttributeAbstractForm(FormBuilderInterface $builder, $name, LocaleTransfer $localeTransfer = null, array $options = [])
    {
        $builder
            ->add($name, 'collection', [
                'type' => new AttributeAbstractForm(
                    $name,
                    $this->productManagementQueryContainer,
                    $this->localeProvider,
                    $localeTransfer
                ),
                'options' => [
                    AttributeAbstractForm::OPTION_ATTRIBUTE => $options,
                ],
                'label' => false,
                'constraints' => [new Callback([
                    'methods' => [
                        function ($attributes, ExecutionContextInterface $context) {
                            foreach ($attributes as $type => $valueSet) {
                                if ($valueSet[AttributeAbstractForm::FIELD_NAME] && empty($valueSet[AttributeAbstractForm::FIELD_VALUE])) {
                                    $context->addViolation(sprintf(
                                        'Please enter value for product attribute "%s" or disable it',
                                        $type
                                    ));
                                }
                            }
                        },
                    ],
                    'groups' => [self::VALIDATION_GROUP_ATTRIBUTE_ABSTRACT]
                ])]
            ]);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array $options
     *
     * @return $this
     */
    protected function addAttributeVariantForm(FormBuilderInterface $builder, array $options = [])
    {
        $builder
            ->add(self::FORM_ATTRIBUTE_VARIANT, 'collection', [
                'type' => new AttributeVariantForm(
                    self::FORM_ATTRIBUTE_VARIANT,
                    $this->productManagementQueryContainer,
                    $this->localeProvider
                ),
                'options' => [
                    AttributeVariantForm::OPTION_ATTRIBUTE => $options,
                ],
                'label' => false,
                'constraints' => [new Callback([
                    'methods' => [
                        function ($attributes, ExecutionContextInterface $context) {
                            foreach ($attributes as $type => $valueSet) {
                                if ($valueSet[AttributeVariantForm::FIELD_NAME] && empty($valueSet[AttributeVariantForm::FIELD_VALUE])) {
                                    $context->addViolation(sprintf(
                                        'Please enter value for variant attribute "%s" or disable it',
                                        $type
                                    ));
                                }
                            }

                            $selectedAttributes = [];
                            foreach ($attributes as $type => $valueSet) {
                                if (!empty($valueSet[AttributeVariantForm::FIELD_VALUE])) {
                                    $selectedAttributes[] = $valueSet[AttributeVariantForm::FIELD_VALUE];
                                    break;
                                }
                            }

                            if (empty($selectedAttributes) && !array_key_exists($context->getGroup(), GeneralForm::$errorFieldsDisplayed)) {
                                $context->addViolation('Please select at least one attribute and its value under Variants');
                                GeneralForm::$errorFieldsDisplayed[$context->getGroup()] = true;
                            }
                        },
                    ],
                    'groups' => [self::VALIDATION_GROUP_ATTRIBUTE_VARIANT]
                ])]
            ]);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array $options
     *
     * @return $this
     */
    protected function addPriceForm(FormBuilderInterface $builder, array $options = [])
    {
        $builder
            ->add(self::FORM_PRICE_AND_TAX, new PriceForm($options), [
                'label' => false,
                'constraints' => [new Callback([
                    'methods' => [
                        function ($dataToValidate, ExecutionContextInterface $context) {
                            if ((int)$dataToValidate[PriceForm::FIELD_PRICE] <= 0) {
                                $context->addViolation('Please enter Price information under Price & Taxes');
                            }

                            if ((int)$dataToValidate[PriceForm::FIELD_TAX_RATE] <= 0) {
                                $context->addViolation('Please enter Tax information under Price & Taxes');
                            }
                        },
                    ],
                    'groups' => [self::VALIDATION_GROUP_PRICE_AND_TAX]
                ])]
            ]);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param string $name
     * @param array $options
     *
     * @return $this
     */
    protected function addImageForm(FormBuilderInterface $builder, $name, array $options = [])
    {
        $builder
            ->add($name, 'collection', [
                'type' => new ImageForm($name),
                'label' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'constraints' => [new Callback([
                    'methods' => [
                        function ($imageSetCollection, ExecutionContextInterface $context) {
                            if (array_key_exists($context->getGroup(), GeneralForm::$errorFieldsDisplayed)) {
                                return;
                            }

                            foreach ($imageSetCollection as $setData) {
                                if (trim($setData[ImageForm::FIELD_SET_NAME]) === '') {
                                    $context->addViolation('Please enter Image Set Name under Images');
                                    GeneralForm::$errorFieldsDisplayed[$context->getGroup()] = true;
                                }

                                foreach ($setData[ImageForm::PRODUCT_IMAGES] as $productImage) {
                                    if (trim($productImage[ImageCollectionForm::FIELD_IMAGE_SMALL]) === '') {
                                        $context->addViolation('Please enter small image url under Images');
                                        GeneralForm::$errorFieldsDisplayed[$context->getGroup()] = true;
                                    }

                                    if (trim($productImage[ImageCollectionForm::FIELD_IMAGE_LARGE]) === '') {
                                        $context->addViolation('Please enter large image url under Images');
                                        GeneralForm::$errorFieldsDisplayed[$context->getGroup()] = true;
                                    }
                                }
                            }
                        },
                    ],
                    'groups' => [self::VALIDATION_GROUP_IMAGE_SET]
                ])]
            ]);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param string $name
     * @param array $options
     *
     * @return $this
     */
    protected function addSeoForm(FormBuilderInterface $builder, $name, array $options = [])
    {
        $builder
            ->add($name, new SeoForm($name), [
                'label' => false,
            ]);

        return $this;
    }

    /**
     * @param string $localeCode
     *
     * @return string
     */
    public static function getLocalizedPrefixName($prefix, $localeCode)
    {
        return $prefix . '_' . $localeCode . '';
    }

    /**
     * @param string $localeCode
     *
     * @return string
     */
    public static function getGeneralFormName($localeCode)
    {
        return self::getLocalizedPrefixName(self::FORM_GENERAL, $localeCode);
    }

    /**
     * @param string $localeCode
     *
     * @return string
     */
    public static function getSeoFormName($localeCode)
    {
        return self::getLocalizedPrefixName(self::FORM_SEO, $localeCode);
    }

    /**
     * @param string $localeCode
     *
     * @return string
     */
    public static function getAbstractAttributeFormName($localeCode)
    {
        return self::getLocalizedPrefixName(self::FORM_ATTRIBUTE_ABSTRACT, $localeCode);
    }

    /**
     * @param string $localeCode
     *
     * @return string
     */
    public static function getImagesFormName($localeCode)
    {
        return self::getLocalizedPrefixName(self::FORM_IMAGE_SET, $localeCode);
    }

}
