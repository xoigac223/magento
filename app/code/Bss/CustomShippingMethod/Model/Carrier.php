<?php
/**
 * BSS Commerce Co.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://bsscommerce.com/Bss-Commerce-License.txt
 *
 * @category   BSS
 * @package    Bss_CustomShippingMethod
 * @author     Extension Team
 * @copyright  Copyright (c) 2017-2018 BSS Commerce Co. ( http://bsscommerce.com )
 * @license    http://bsscommerce.com/Bss-Commerce-License.txt
 */
namespace Bss\CustomShippingMethod\Model;

use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;

/**
 * Class Carrier
 *
 * @package Bss\CustomShippingMethod\Model
 */
class Carrier extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = "customshippingmethod";

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    protected $rateMethodFactory;

    /**
     * @var \Bss\CustomShippingMethod\Model\Rate\ResultFactory
     */
    protected $rateResultFactory;

    /**
     * @var \Bss\CustomShippingMethod\Helper\Data
     */
    protected $helper;

    /**
     * Carrier constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Bss\CustomShippingMethod\Model\Rate\ResultFactory $rateResultFactory
     * @param \Bss\CustomShippingMethod\Helper\Data $helper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Bss\CustomShippingMethod\Model\Rate\ResultFactory $rateResultFactory,
        \Bss\CustomShippingMethod\Helper\Data $helper,
        array $data = []
    ) {
        $this->rateMethodFactory = $rateMethodFactory;
        $this->rateResultFactory = $rateResultFactory;
        $this->helper = $helper;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * Collect Rate.
     *
     * @param RateRequest $request
     * @return bool|\Magento\Framework\DataObject|Result
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function collectRates(RateRequest $request)
    {
        $result = $this->rateResultFactory->create();

        $inclTax = $request->getBaseSubtotalInclTax();

        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $array = $this->sortBySortOrder();

        foreach ($array as $customMethod) {
            $minOrderAmount = $this->checkOrderAmount($customMethod['minimum_order_amount']);
            $maxOrderAmount = $this->checkOrderAmount($customMethod['maximum_order_amount']);
            if (!$this->checkMethodAvailable($customMethod, $request)) {
                continue;
            } else {
                if ($inclTax >= $minOrderAmount && $inclTax <= $maxOrderAmount) {
                    $result->append($this->checkEnabled($request, $customMethod));
                }
            }
        }
        return $result;
    }

    /**
     * Check Enabled.
     *
     * @param RateRequest $request
     * @param array $customMethod
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function checkEnabled($request, $customMethod)
    {
        switch ($customMethod['enabled']) {
            case 1:
                if ($this->isAdmin()) {
                    $method = $this->createResultMethod($request, $customMethod);
                    return $method;
                }
                break;
            case 2:
                if (!$this->isAdmin()) {
                    $method = $this->createResultMethod($request, $customMethod);
                    return $method;
                }
                break;
            case 3:
                $method = $this->createResultMethod($request, $customMethod);
                return $method;
                break;
        }
    }

    /**
     * Create Method.
     *
     * @param RateRequest $request
     * @param array $customMethod
     * @return \Magento\Quote\Model\Quote\Address\RateResult\Method
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function createResultMethod($request, $customMethod)
    {
        $storeIds = $this->helper->getStoreView()->selectDB($customMethod['id']);
        $storeIdCur =$this->helper->getStoreMana()->getStore()->getId();
        if (in_array('0', $storeIds)) {
            $freeBoxes = $this->getFreeBoxesCount($request);
            $this->setFreeBoxes($freeBoxes);
            $shippingPrice = $this->getShippingPrice($request, $freeBoxes, $customMethod);
            $method = $this->rateMethodFactory->create();
            $method->setCarrier('customshippingmethod');
            $method->setCarrierTitle($this->getConfigData('title'));
            $method->setMethod($customMethod['id']);
            $method->setMethodTitle($customMethod['name']);
            $method->setPrice($shippingPrice);
            $method->setCost($shippingPrice);
            return $method;
        } elseif (in_array($storeIdCur, $storeIds)) {
            $freeBoxes = $this->getFreeBoxesCount($request);
            $this->setFreeBoxes($freeBoxes);
            $shippingPrice = $this->getShippingPrice($request, $freeBoxes, $customMethod);
            $method = $this->rateMethodFactory->create();
            $this->setSortOrder($customMethod['sort_order']);
            $method->setCarrier('customshippingmethod');
            $method->setCarrierTitle($this->getConfigData('title'));
            $method->setMethod($customMethod['id']);
            $method->setMethodTitle($customMethod['name']);
            $method->setPrice($shippingPrice);
            $method->setCost($shippingPrice);
            return $method;
        }
    }

    /**
     * Check Order.
     *
     * @param array $orderAmount
     * @return float|bool
     */
    private function checkOrderAmount($orderAmount)
    {
        if ($orderAmount == null) {
            return true;
        } else {
            return (float)$orderAmount;
        }
    }

    /**
     * Allow Methods.
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        foreach ($this->getCollectionMethod() as $customMethod) {
            return [$customMethod['id'] => $customMethod['name']];
        }
    }

    /**
     * Get Shipping Price
     *
     * @param RateRequest $request
     * @param int $freeBoxes
     * @param array $customMethod
     * @return float
     */
    private function getShippingPrice(RateRequest $request, $freeBoxes, $customMethod)
    {
        $shippingPrice = false;
        $configPrice =  $customMethod['price'];
        if ($customMethod['type'] === 'O') {
            // per order
            $shippingPrice = $this->helper->itemPriceCalculator()
                ->getShippingPricePerOrder($request, $configPrice, $freeBoxes);
        } elseif ($customMethod['type'] === 'I') {
            // per item
            $shippingPrice = $this->helper->itemPriceCalculator()
                ->getShippingPricePerItem($request, $configPrice, $freeBoxes);
        }
        $handlingFee = (float)$customMethod['handling_fee'];
        $handlingType = $customMethod['calculate_handling_fee'];
        if (!$handlingType) {
            $handlingType = self::HANDLING_TYPE_FIXED;
        }
        $handlingAction = $this->getConfigData('handling_action');
        if (!$handlingAction) {
            $handlingAction = self::HANDLING_ACTION_PERORDER;
        }

        $shippingPrice = $handlingAction == self::HANDLING_ACTION_PERPACKAGE ? $this->_getPerpackagePrice(
            $shippingPrice,
            $handlingType,
            $handlingFee
        ) : $this->_getPerorderPrice(
            $shippingPrice,
            $handlingType,
            $handlingFee
        );
        if ($shippingPrice !== false && $request->getPackageQty() == $freeBoxes) {
            $shippingPrice = '0.00';
        }
        return $shippingPrice;
    }

    /**
     * Custom Check Available Ship Countries.
     *
     * @param array $method
     * @param RateRequest $request
     * @return $this|bool
     */
    protected function checkMethodAvailable($method, $request)
    {
        $speCountriesAllow = (int)$method['applicable_countries'];

        if ($speCountriesAllow && $speCountriesAllow == '1') {
            $availableCountries = [];
            if ($method['specific_countries']) {
                $availableCountries = explode(',', $method['specific_countries']);
            }
            if ($availableCountries && in_array($request->getDestCountryId(), $availableCountries)) {
                return $this;
            } else {
                return false;
            }
        }
        return $this;
    }

    /**
     * Is Admin.
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function isAdmin()
    {

        return $this->helper->getState()->getAreaCode() == FrontNameResolver::AREA_CODE;
    }

    /**
     * Free Boxes.
     *
     * @param mixed $item
     * @return mixed
     */
    private function getFreeBoxesCountFromChildren($item)
    {
        $freeBoxes = 0;
        foreach ($item->getChildren() as $child) {
            if ($child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
                $freeBoxes += $item->getQty() * $child->getQty();
            }
        }
        return $freeBoxes;
    }

    /**
     * Free Box count.
     *
     * @param RateRequest $request
     * @return int
     */
    private function getFreeBoxesCount(RateRequest $request)
    {
        $freeBoxes = 0;
        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                    continue;
                }

                if ($item->getHasChildren() && $item->isShipSeparately()) {
                    $freeBoxes += $this->getFreeBoxesCountFromChildren($item);
                } elseif ($item->getFreeShipping()) {
                    $freeBoxes += $item->getQty();
                }
            }
        }
        return $freeBoxes;
    }

    /**
     * Get CollectionMethod.
     *
     * @return array
     */
    public function getCollectionMethod()
    {
        $collection = $this->helper->getCollectionMethod()->create();
        return $collection->getData();
    }
    /**
     * Sort By Sort Order
     *
     * @return array.
     */
    protected function sortBySortOrder()
    {
        $array = $this->getCollectionMethod();
        uasort($array, function ($a, $b) {
            return $a['sort_order'] - $b['sort_order'];
        });
        return $array;
    }
}
