<?php namespace Sitios\Bamboo;

use Event;
use System\Classes\PluginBase;

use Omnipay\Omnipay;
use Lovata\OmnipayShopaholic\Classes\Helper\PaymentGateway;
use Sitios\Bamboo\Classes\Event\ProcessPayment;
use Sitios\Bamboo\Classes\Event\ExtendFieldHandler;
use Sitios\Bamboo\Classes\Event\PaymentReturnUrl;

/**
 * Class Plugin
 * @package Sitios\Bamboo
 * @author Marlon Freire, marlonfg91@gmail.com, Sitios Agencia Digital
 */
class Plugin extends PluginBase
{
    public $require = ['Lovata.Toolbox', 'Lovata.Shopaholic', 'Lovata.OrdersShopaholic', 'Lovata.OmnipayShopaholic'];

    /**
     * Boot plugin method
     */
    public function boot()
    {
        $factory = Omnipay::getFactory();
        $factory->register('BambooPayment');

        $this->addEventListener();
    }

    /**
     * Add event listeners
     */
    protected function addEventListener()
    {
        //Register Gateways
        Event::subscribe(ExtendFieldHandler::class);
        Event::listen(PaymentGateway::EVENT_GET_PAYMENT_GATEWAY_PURCHASE_DATA, PaymentReturnUrl::class);
    }
}