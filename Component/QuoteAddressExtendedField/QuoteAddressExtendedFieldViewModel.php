<?php

declare(strict_types=1);

namespace Loki\QuoteAddressExtended\Component\QuoteAddressExtendedField;

use Loki\Components\Attribute\JsProperty;
use LokiCheckout\Core\Component\Base\Generic\CheckoutViewModel;

class QuoteAddressExtendedFieldViewModel extends CheckoutViewModel
{
    #[JsProperty(name: 'step')]
    public function getStep(): string
    {
        $step = parent::getStep();
        if ($step !== 'none') {
            return $step;
        }

        if ($this->getContext()->getQuote()->isVirtual()) {
            return 'billing';
        }

        return 'shipping';
    }
}
