<?php

namespace Oro\Bundle\PromotionBundle\Tests\Unit\Context;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\CustomerBundle\Entity\Customer;
use Oro\Bundle\CustomerBundle\Entity\CustomerGroup;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\OrderBundle\Entity\OrderAddress;
use Oro\Bundle\OrderBundle\Entity\OrderLineItem;
use Oro\Bundle\PricingBundle\SubtotalProcessor\Model\Subtotal;
use Oro\Bundle\PricingBundle\SubtotalProcessor\Model\SubtotalProviderInterface;
use Oro\Bundle\PromotionBundle\Context\ContextDataConverterInterface;
use Oro\Bundle\PromotionBundle\Context\CriteriaDataProvider;
use Oro\Bundle\PromotionBundle\Context\OrderContextDataConverter;
use Oro\Bundle\PromotionBundle\Discount\Converter\OrderLineItemsToDiscountLineItemsConverter;
use Oro\Bundle\PromotionBundle\Discount\DiscountLineItem;
use Oro\Bundle\PromotionBundle\Discount\Exception\UnsupportedSourceEntityException;
use Oro\Bundle\ScopeBundle\Manager\ScopeManager;
use Oro\Bundle\ScopeBundle\Model\ScopeCriteria;
use Oro\Bundle\WebsiteBundle\Entity\Website;

class OrderContextDataConverterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CriteriaDataProvider|\PHPUnit_Framework_MockObject_MockObject
     */
    private $criteriaDataProvider;

    /**
     * @var OrderLineItemsToDiscountLineItemsConverter|\PHPUnit_Framework_MockObject_MockObject
     */
    private $lineItemsConverter;

    /**
     * @var ScopeManager|\PHPUnit_Framework_MockObject_MockObject
     */
    private $scopeManager;

    /**
     * @var SubtotalProviderInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $lineItemSubtotalProvider;

    /**
     * @var OrderContextDataConverter
     */
    private $converter;

    protected function setUp()
    {
        $this->criteriaDataProvider = $this->createMock(CriteriaDataProvider::class);
        $this->lineItemsConverter = $this->createMock(OrderLineItemsToDiscountLineItemsConverter::class);
        $this->scopeManager = $this->createMock(ScopeManager::class);
        $this->lineItemSubtotalProvider = $this->createMock(SubtotalProviderInterface::class);

        $this->converter = new OrderContextDataConverter(
            $this->criteriaDataProvider,
            $this->lineItemsConverter,
            $this->scopeManager,
            $this->lineItemSubtotalProvider
        );
    }

    public function testSupportsForWrongEntity()
    {
        $entity = new \stdClass();
        $this->assertFalse($this->converter->supports($entity));
    }

    public function testSupportsForCheckoutWithNonShoppingListAsSource()
    {
        $this->assertFalse($this->converter->supports(new \stdClass()));
    }

    public function testSupports()
    {
        $this->assertTrue($this->converter->supports(new Order()));
    }

    public function testGetContextDataWhenThrowsException()
    {
        $entity = new \stdClass();
        $this->expectException(UnsupportedSourceEntityException::class);
        $this->expectExceptionMessage('Source entity "stdClass" is not supported.');

        $this->converter->getContextData($entity);
    }

    public function testGetContextData()
    {
        $customerGroup = new CustomerGroup();
        $customer = new Customer();
        $customerUser = new CustomerUser();
        $billingAddress = new OrderAddress();
        $shippingAddress = new OrderAddress();
        $website = new Website();
        $shippingMethod = 'some shipping method';

        $entity = new Order();
        $entity->setCustomerUser($customerUser);
        $entity->setBillingAddress($billingAddress);
        $entity->setShippingAddress($shippingAddress);
        $entity->setEstimatedShippingCostAmount(10.0);
        $entity->setCurrency('USD');
        $entity->setShippingMethod($shippingMethod);

        $this->criteriaDataProvider->expects($this->once())
            ->method('getCustomerUser')
            ->with($entity)
            ->willReturn($customerUser);
        $this->criteriaDataProvider->expects($this->once())
            ->method('getCustomer')
            ->with($entity)
            ->willReturn($customer);
        $this->criteriaDataProvider->expects($this->once())
            ->method('getCustomerGroup')
            ->with($entity)
            ->willReturn($customerGroup);
        $this->criteriaDataProvider->expects($this->once())
            ->method('getWebsite')
            ->with($entity)
            ->willReturn($website);

        $discountLineItems = $this->getDiscountLineItems($entity);
        $scopeCriteria = $this->getScopeCriteria($customer, $customerGroup, $website);
        $subtotalAmount = $this->getSubtotalAmount($entity);

        $this->assertEquals([
            ContextDataConverterInterface::CUSTOMER_USER => $customerUser,
            ContextDataConverterInterface::CUSTOMER => $customer,
            ContextDataConverterInterface::CUSTOMER_GROUP => $customerGroup,
            ContextDataConverterInterface::LINE_ITEMS => $discountLineItems,
            ContextDataConverterInterface::SUBTOTAL => $subtotalAmount,
            ContextDataConverterInterface::CURRENCY => $entity->getCurrency(),
            ContextDataConverterInterface::CRITERIA => $scopeCriteria,
            ContextDataConverterInterface::BILLING_ADDRESS => $billingAddress,
            ContextDataConverterInterface::SHIPPING_ADDRESS => $shippingAddress,
            ContextDataConverterInterface::SHIPPING_COST => Price::create(10.0, 'USD'),
            ContextDataConverterInterface::SHIPPING_METHOD => $shippingMethod,
        ], $this->converter->getContextData($entity));
    }

    /**
     * @param Order $entity
     * @return DiscountLineItem[]
     */
    private function getDiscountLineItems(Order $entity): array
    {
        $lineItems = [new OrderLineItem()];
        $discountLineItems = [new DiscountLineItem()];
        $entity->setLineItems(new ArrayCollection($lineItems));
        $this->lineItemsConverter->expects($this->once())
            ->method('convert')
            ->with($lineItems)
            ->willReturn($discountLineItems);

        return $discountLineItems;
    }

    /**
     * @param Customer $customer
     * @param CustomerGroup $customerGroup
     * @param Website $website
     * @return ScopeCriteria
     */
    private function getScopeCriteria(Customer $customer, CustomerGroup $customerGroup, Website $website): ScopeCriteria
    {
        $scopeContext = [
            'customer' => $customer,
            'customerGroup' => $customerGroup,
            'website' => $website
        ];
        $scopeCriteria = new ScopeCriteria([], []);
        $this->scopeManager->expects($this->once())
            ->method('getCriteria')
            ->with('promotion', $scopeContext)
            ->willReturn($scopeCriteria);

        return $scopeCriteria;
    }

    /**
     * @param Order $entity
     * @return float
     */
    private function getSubtotalAmount(Order $entity)
    {
        $subtotalAmount = 100.0;
        $this->lineItemSubtotalProvider->expects($this->once())
            ->method('getSubtotal')
            ->with($entity)
            ->willReturn((new Subtotal())->setAmount($subtotalAmount));

        return $subtotalAmount;
    }
}
