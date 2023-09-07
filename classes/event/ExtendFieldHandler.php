<?php namespace Sitios\Bamboo\Classes\Event;

use Cms\Classes\Page;
use Lang;
use Event;
use Omnipay\Omnipay;

use Lovata\OmnipayShopaholic\Classes\Helper\PaymentGateway;

use Lovata\OrdersShopaholic\Models\Status;
use Lovata\OrdersShopaholic\Models\OrderProperty;
use Lovata\OrdersShopaholic\Models\PaymentMethod;
use Lovata\OrdersShopaholic\Controllers\PaymentMethods;

class ExtendFieldHandler
{
    /**
     * Add listeners
     * @param \Illuminate\Events\Dispatcher $obEvent
     */
    public function subscribe($obEvent)
    {
        $obEvent->listen('backend.form.extendFields', function ($obWidget) {
            $this->extendPaymentMethodFields($obWidget);
            return false;
        }, 1);
    }

    /**
     * Extend settings fields
     * @param \Backend\Widgets\Form $obWidget
     */
    protected function extendPaymentMethodFields($obWidget)
    {
        // Only for the Settings controller
        if (!$obWidget->getController() instanceof PaymentMethods || $obWidget->isNested) {
            return;
        }

        // Only for the Settings model
        if (!$obWidget->model instanceof PaymentMethod || empty($obWidget->model->gateway_id) || !class_exists(Omnipay::class)) {
            return;
        }

        //Get payment gateway list
        $arGatewayList = PaymentGateway::getOmnipayGatewayList();
        if (empty($arGatewayList) || !in_array($obWidget->model->gateway_id, $arGatewayList)) {
            return;
        }

        $this->addGatewayPropertyFields($obWidget->model, $obWidget);
        $this->addUserFieldList($obWidget);
    }

    /**
     * Add gateway property list
     * @param PaymentMethod         $obPaymentMethod
     * @param \Backend\Widgets\Form $obWidget
     */
    protected function addGatewayPropertyFields($obPaymentMethod, $obWidget)
    {
        if (!$obPaymentMethod->name == 'BambooPayment') {
            return;
        }

        $arStatusOptions = $this->getStatusOptions();
        $arRedirectOptions = $this->getRedirectOptions();

        $obWidget->addTabFields([
            
            'gateway_property[public_account_key]' => [
                'label' => 'Public Account Key',
                'tab'   => 'lovata.ordersshopaholic::lang.tab.gateway',
                'type'  => 'text',
                'span'  => 'left',
                'required' => true
            ],
            'gateway_property[private_account_key]' => [
                'label' => 'Private Account Key',
                'tab'   => 'lovata.ordersshopaholic::lang.tab.gateway',
                'type'  => 'text',
                'span'  => 'right',
                'required' => true
            ],
            'gateway_property[unique_id]' => [
                'label' => 'Unique ID',
                'tab'   => 'lovata.ordersshopaholic::lang.tab.gateway',
                'type'  => 'text',
                'span'  => 'left',
                'required' => true
            ],
            'gateway_property[access_token]' => [
                'label' => 'Access Token',
                'tab'   => 'lovata.ordersshopaholic::lang.tab.gateway',
                'type'  => 'text',
                'span'  => 'left',
                'required' => true
            ],

            'gateway_property[test_access_token]' => [
                'label' => 'Test Access Token',
                'tab'   => 'lovata.ordersshopaholic::lang.tab.gateway',
                'type'  => 'text',
                'span'  => 'right',
                'required' => true
            ],

            'gateway_property[returnUrl]' => [
                'label' => 'Return URL',
                'tab' => 'lovata.ordersshopaholic::lang.tab.gateway',
                'type' => 'dropdown',
                'span' => 'left',
                'required' => true,
                'placeholder' => 'Emtpy',
                'options' => $arRedirectOptions,
            ],

            'gateway_property[testMode]' => [
                'label' => 'Test Mode',
                'tab'   => 'lovata.ordersshopaholic::lang.tab.gateway',
                'type'  => 'checkbox',
                'span'  => 'right'
            ],
        ]);
    }

    protected function getStatusOptions()
    {
        return Status::all()
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function getRedirectOptions()
    {
        return Page::all()
            ->pluck('url', 'fileName')
            ->toArray();
    }

    /**
     * Add user fields
     * @param \Backend\Widgets\Form $obWidget
     */
    protected function addUserFieldList($obWidget)
    {
        $arPropertyList = $this->getPropertyOptions();
        if (empty($arPropertyList)) {
            return;
        }

        $sSpan = 'left';
        $arFieldList = [];
        $arUserFieldList = [
            'firstName',
            'lastName',
            'number',
            'expiryMonth',
            'expiryYear',
            'startMonth',
            'startYear',
            'cvv',
            'billingAddress1',
            'billingAddress2',
            'billingCity',
            'billingPostcode',
            'billingState',
            'billingCountry',
            'billingPhone',
            'shippingAddress1',
            'shippingAddress2',
            'shippingCity',
            'shippingPostcode',
            'shippingState',
            'shippingCountry',
            'shippingPhone',
            'company',
            'email',
        ];

        foreach ($arUserFieldList as $sFieldName) {
            $arFieldList['gateway_property['.$sFieldName.']'] = $this->getUserFieldData($sFieldName, $sSpan, $arPropertyList);
            $sSpan = $sSpan == 'left' ? 'right' : 'left';
        }

        $obWidget->addTabFields($arFieldList);
    }

    /**
     * @return array
     */
    protected function getPropertyOptions()
    {
        $arResult = (array) OrderProperty::active()->pluck('name', 'code')->all();
        if (empty($arResult)) {
            return [];
        }

        foreach ($arResult as &$sName) {
            $sName = Lang::get($sName);
        }

        return $arResult;
    }

    /**
     * Get user field config
     * @param string $sField
     * @param string $sSpan
     * @param array  $arPropertyList
     * @return array
     */
    protected function getUserFieldData($sField, $sSpan, $arPropertyList)
    {
        $sLabel = Lang::get('lovata.ordersshopaholic::lang.field.gateway_field_value', ['field' => $sField]);

        $arResult = [
            'label'       => $sLabel,
            'tab'         => 'lovata.ordersshopaholic::lang.tab.gateway',
            'emptyOption' => 'lovata.toolbox::lang.field.empty',
            'type'        => 'dropdown',
            'hidden'      => 'true',
            'span'        => $sSpan,
            'options'     => $arPropertyList,
        ];

        return $arResult;
    }
}