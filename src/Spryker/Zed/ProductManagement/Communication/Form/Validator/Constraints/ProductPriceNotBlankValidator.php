<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\ProductManagement\Communication\Form\Validator\Constraints;

use ArrayObject;
use Spryker\Zed\ProductManagement\Communication\Form\Product\Price\ProductMoneyCollectionType;
use Spryker\Zed\ProductManagement\Communication\Form\ProductFormAdd;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ProductPriceNotBlankValidator extends ConstraintValidator
{
    /**
     * @param mixed $value
     * @param \Spryker\Zed\ProductManagement\Communication\Form\Validator\Constraints\ProductPriceNotBlank|\Symfony\Component\Validator\Constraint $constraint
     *
     * @return void
     */
    public function validate($value, Constraint $constraint): void
    {
        if ($value === null || $value === '') {
            return;
        }

        /** @phpstan-var \Spryker\Zed\ProductManagement\Communication\Form\Validator\Constraints\ProductPriceNotBlank $constraint */
        $this->validateProductPriceNotBlank($value, $constraint);
    }

    /**
     * @param mixed $value
     * @param \Spryker\Zed\ProductManagement\Communication\Form\Validator\Constraints\ProductPriceNotBlank $constraint
     *
     * @return void
     */
    protected function validateProductPriceNotBlank($value, ProductPriceNotBlank $constraint)
    {
        $formData = $this->context->getRoot()->getData();

        if ($formData[ProductFormAdd::FORM_PRICE_DIMENSION]) {
            return;
        }

        foreach ($this->getGrouppedPricesArray($value) as $priceGroup) {
            if ($this->validatePriceGroup($priceGroup)) {
                return;
            }
        }

        $this->context->buildViolation($constraint->message)->addViolation();
    }

    /**
     * @param array<\Generated\Shared\Transfer\PriceProductTransfer> $priceGroup
     *
     * @return bool
     */
    protected function validatePriceGroup(array $priceGroup)
    {
        foreach ($priceGroup as $priceProductTransfer) {
            $moneyValueTransfer = $priceProductTransfer->getMoneyValue();

            if ($moneyValueTransfer->getGrossAmount() !== null || $moneyValueTransfer->getNetAmount() !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \ArrayObject<string, \Generated\Shared\Transfer\PriceProductTransfer> $productPrices
     *
     * @return array<string, array<int, \Generated\Shared\Transfer\PriceProductTransfer>>
     */
    protected function getGrouppedPricesArray(ArrayObject $productPrices)
    {
        $groupedPrices = [];

        foreach ($productPrices as $compositeKey => $priceProductTransfer) {
            $groupedPrices[$this->getGroupKeyFromCompositePriceKey($compositeKey)][] = $priceProductTransfer;
        }

        return $groupedPrices;
    }

    /**
     * @param string $compositeKey
     *
     * @return string
     */
    protected function getGroupKeyFromCompositePriceKey(string $compositeKey)
    {
        $keyPartials = explode(ProductMoneyCollectionType::PRICE_DELIMITER, $compositeKey);

        return $keyPartials[0] . ProductMoneyCollectionType::PRICE_DELIMITER . $keyPartials[1];
    }
}
