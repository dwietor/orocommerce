<?php

namespace Oro\Bundle\PaymentBundle\Method\Provider;

use Oro\Bundle\PaymentBundle\Context\PaymentContextInterface;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodProvidersRegistry;
use Oro\Bundle\PaymentBundle\Provider\PaymentMethodsConfigsRulesProviderInterface;

class PaymentMethodProvider
{
    /**
     * @var PaymentMethodProvidersRegistry
     */
    private $paymentMethodRegistry;

    /**
     * @var PaymentMethodsConfigsRulesProviderInterface
     */
    private $paymentMethodsConfigsRulesProvider;

    /**
     * @param PaymentMethodProvidersRegistry $paymentMethodRegistry
     * @param PaymentMethodsConfigsRulesProviderInterface $paymentMethodsConfigsRulesProvider
     */
    public function __construct(
        PaymentMethodProvidersRegistry $paymentMethodRegistry,
        PaymentMethodsConfigsRulesProviderInterface $paymentMethodsConfigsRulesProvider
    ) {
        $this->paymentMethodRegistry = $paymentMethodRegistry;
        $this->paymentMethodsConfigsRulesProvider = $paymentMethodsConfigsRulesProvider;
    }

    /**
     * @param PaymentContextInterface $context
     *
     * @return PaymentMethodInterface[]
     */
    public function getApplicablePaymentMethods(PaymentContextInterface $context)
    {
        $paymentMethodsConfigsRules = $this->paymentMethodsConfigsRulesProvider
            ->getFilteredPaymentMethodsConfigs($context);

        $paymentMethods = [];

        foreach ($paymentMethodsConfigsRules as $paymentMethodsConfigsRule) {
            foreach ($paymentMethodsConfigsRule->getMethodConfigs() as $methodConfig) {
                foreach ($this->paymentMethodRegistry->getPaymentMethodProviders() as $provider) {
                    $paymentMethod = $provider
                        ->getPaymentMethod($methodConfig->getType());
                    if ($paymentMethod->isApplicable($context)) {
                        $paymentMethods[$methodConfig->getType()] = $paymentMethod;
                    }
                }
            }
        }

        return $paymentMethods;
    }
}
