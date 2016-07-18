<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\ProductManagement\Communication\Form;

use Spryker\Zed\Gui\Communication\Form\Type\Select2ComboBoxType;
use Spryker\Zed\ProductManagement\Communication\Form\Constraints\AttributeFieldNotBlank;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ProductFormAttributeAbstract extends AbstractSubForm
{

    const FIELD_NAME = 'name';
    const FIELD_VALUE = 'value';

    const LABEL = 'label';
    const MULTIPLE = 'multiple';
    const PRODUCT_SPECIFIC = 'product_specific';
    const NAME_DISABLED = 'name_disabled';
    const VALUE_DISABLED = 'value_disabled';
    const INPUT = 'input';

    const OPTION_ATTRIBUTE_ABSTRACT = 'option_attribute_abstract';

    /**
     * @var array
     */
    protected $attributeValues;


    /**
     * @param \Symfony\Component\OptionsResolver\OptionsResolverInterface $resolver
     *
     * @return void
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        parent::setDefaultOptions($resolver);

        $resolver->setRequired(self::OPTION_ATTRIBUTE_ABSTRACT);
        $resolver->setRequired(ProductFormAdd::SUB_FORM_NAME);
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array $options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $this
            ->addCheckboxNameField($builder, $options)
            ->addValueField($builder, $options);
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     * @param array $options
     *
     * @return $this
     */
    protected function addCheckboxNameField(FormBuilderInterface $builder, array $options)
    {
        $attributes = $options[ProductFormAttributeAbstract::OPTION_ATTRIBUTE_ABSTRACT];

        $name = $builder->getName();
        $label = $name;
        $isDisabled = true;

        if (isset($attributes[$name])) {
            $label = $attributes[$name][self::LABEL];
            $isDisabled = $attributes[$name][self::NAME_DISABLED];
        }

        $builder
            ->add(self::FIELD_NAME, 'checkbox', [
                'label' => $label,
                'disabled' => $isDisabled,
                'attr' => [
                    'class' => 'attribute_metadata_checkbox',
                    'product_specific' => $attributes[$name][self::PRODUCT_SPECIFIC]
                ],
            ]);

        return $this;
    }

    /**
     * @param \Symfony\Component\Form\FormBuilderInterface $builder
     *
     * @return $this
     */
    protected function addValueField(FormBuilderInterface $builder, array $options = [])
    {
        $attributes = $options[ProductFormAttributeAbstract::OPTION_ATTRIBUTE_ABSTRACT];

        $name = $builder->getName();
        $isDisabled = $attributes[$name][self::VALUE_DISABLED];
        $input = $attributes[$name][self::INPUT];

        $builder->add(self::FIELD_VALUE, $input, [
            'disabled' => $isDisabled,
            'label' => false,
            'attr' => [
                'style' => 'width: 250px !important',
                'class' => 'attribute_metadata_value',
                'product_specific' => $attributes[$name][self::PRODUCT_SPECIFIC]
            ],
            'constraints' => [
                new Callback([
                    'methods' => [
                        function ($dataToValidate, ExecutionContextInterface $context) {
                            //TODO more sophisticated validation
                            if (!($dataToValidate)) {
                                //$context->addViolation('Please enter attribute value.');
                            }
                        },
                    ],
                ]),
            ]
/*            'constraints' => [
                new AttributeFieldNotBlank([
                    'attributeFieldValue' => self::FIELD_VALUE,
                    'attributeCheckboxFieldName' => self::FIELD_NAME,
                ]),
            ],*/
        ]);

        return $this;
    }

}
