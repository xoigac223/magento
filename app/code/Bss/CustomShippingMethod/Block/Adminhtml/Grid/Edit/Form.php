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
namespace Bss\CustomShippingMethod\Block\Adminhtml\Grid\Edit;

use Bss\CustomShippingMethod\Model\Status;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Directory\Model\Config\Source\Country;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Shipping\Model\Config\Source\Allspecificcountries;
use Magento\Store\Model\System\Store;

/**
 * Adminhtml Add New Form.
 */
class Form extends Generic
{
    /**
     * @var Store
     */
    protected $systemStore;

    /**
     * @var Status
     */
    protected $status;

    /**
     * @var Country
     */
    protected $country;

    /**
     * @var Allspecificcountries
     */
    protected $allCountries;

    /**
     * Form constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param Country $country
     * @param Allspecificcountries $allSpecificCountries
     * @param Store $systemStore
     * @param Status $status
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        Country $country,
        Allspecificcountries $allSpecificCountries,
        Store $systemStore,
        Status $status,
        array $data = []
    ) {
        $this->status = $status;
        $this->country = $country;
        $this->allCountries= $allSpecificCountries;
        $this->systemStore = $systemStore;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * Prepare Form
     *
     * @return Generic
     * @throws LocalizedException
     */
    protected function _prepareForm(): Generic
    {
        $model = $this->_coreRegistry->registry('row_data');
        $form = $this->_formFactory->create(
            ['data' => [
                'id' => 'edit_form',
                'enctype' => 'multipart/form-data',
                'action' => $this->getData('action'),
                'method' => 'post'
            ]
            ]
        );
        $form->setHtmlIdPrefix('bss_');
        if ($model->getEntityId()) {
            $fieldset = $form->addFieldset(
                'base_fieldset',
                ['legend' => __('Edit Custom Method'), 'class' => 'fieldset-wide']
            );
            $fieldset->addField('id', 'hidden', ['name' => 'id']);
        } else {
            $fieldset = $form->addFieldset(
                'base_fieldset',
                ['legend' => __('Add Custom Method'), 'class' => 'fieldset-wide']
            );
            $fieldset->addField('id', 'hidden', ['values'=>""]);
        }
        $fieldset->addField(
            'enabled',
            'select',
            [
                'name' => 'enabled',
                'label' => __('Active'),
                'id' => 'enabled',
                'title' => __('Active'),
                'values' => $this->status->getOptionArray()
            ]
        );
        $fieldset->addField(
            'name',
            'text',
            [
                'name' => 'name',
                'label' => __('Name'),
                'id' => 'name',
                'required' => true,
                'title' => __('Name')
            ]
        );
        $fieldset->addField(
            'type',
            'select',
            [
                'name' => 'type',
                'label' => __('Type'),
                'id' => 'type',
                'title' => __('Type'),
                'values' => [
                        ["value" =>'',"label" => __("None")],
                        ["value" => "O","label" => __("Per Order")],
                        ["value"  => "I","label" => __("Per Item")]
                    ],
                'value'=> "I"
            ]
        );
        $fieldset->addField(
            'price',
            'text',
            [
                'name' => 'price',
                'label' => __('Price'),
                'id' => 'price',
                'title' => __('Price'),
                'class' => "validate-number validate-not-negative-number"
            ]
        );
        $this->addForm($fieldset);
        $this->setChild(
            'form_after',
            $this->getLayout()->createBlock(Template::class)
                ->setTemplate('Bss_CustomShippingMethod::countriesjs.phtml')
        );
        if ($model->getData()) {
            $form->setValues($model->getData());
        }
        $form->setUseContainer(true);
        $this->setForm($form);
        return parent::_prepareForm();
    }

    /**
     * Add Form.
     *
     * @param Fieldset $fieldset
     */
    public function addForm($fieldset)
    {
        $fieldset->addField(
            'calculate_handling_fee',
            'select',
            [
                'name' => 'calculate_handling_fee',
                'label' => __('Calculate Handling Fee'),
                'values' => [
                        ["value" => "F","label" => __('Fixed')],
                        ["value" => "P","label" => __('Percent')]
                    ]
            ]
        );
        $fieldset->addField(
            'handling_fee',
            'text',
            [
                'name' => 'handling_fee',
                'label' => __('Handling Fee'),
                'id' => 'handling_fee',
                'title' => __('Handling Fee'),
                'class' => "validate-number validate-not-negative-number"
            ]
        );
        $fieldset->addField(
            'applicable_countries',
            'select',
            [
                'name' => 'applicable_countries',
                'label' => __('Ship to Applicable Countries'),
                'id' => 'applicable_countries',
                'values' =>$this->allCountries->toOptionArray()
            ]
        );
        $fieldset->addField(
            'specific_countries',
            'multiselect',
            [
                'name' => 'specific_countries',
                'label' => __('Ship to Specific Countries'),
                'id' => 'specific_countries',
                'values' =>$this->country->toOptionArray(true, '')
            ]
        );
        $fieldset->addField(
            'minimum_order_amount',
            'text',
            [
                'name' => 'minimum order amount',
                'label' => __('Minimum order Amount'),
                'id' => 'minimum order amount',
                'class' => "validate-number validate-not-negative-number"
            ]
        );
        $fieldset->addField(
            'maximum_order_amount',
            'text',
            [
                'name' => 'maximum order amount',
                'label' => __('Maximum order Amount'),
                'id' => 'maximum order amount',
                'class' => "validate-number validate-not-negative-number"
            ]
        );
        $fieldset->addField(
            'sort_order',
            'text',
            [
                'name' => 'sort order',
                'label' => __('Sort Order'),
                'id' => 'sort order',
                'class' => "validate-number validate-not-negative-number"
            ]
        );
        $fieldset->addField(
            'store_id',
            'multiselect',
            [
                'name' => 'store_id',
                'label' => __('Store View'),
                'id' => 'store_id',
                'required' => true,
                'values' => $this->systemStore->getStoreValuesForForm(false, true)
            ]
        );
    }
}
