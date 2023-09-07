<?php namespace Sitios\Bamboo\Classes\Event;

use Cms\Classes\Page;
use October\Rain\Router\Router;

class PaymentReturnUrl
{
    public function handle($obOrder, $obPaymentMethod, $arPurchaseData)
    {
        if ($obPaymentMethod->gateway_id != 'BambooPayment') {
            return;
        }

        if(!$obPaymentMethod->gateway_property['returnUrl']) {
            throw new \ValidationException(['returnUrl' => 'Sorry, returnUrl is empty! Please fill it up!!']);
        }
        
        $obRouter = new Router;
        $obPage = Page::find($obPaymentMethod->gateway_property['returnUrl']);

        $arPurchaseData['transactionId']    = $obOrder->secret_key;
        $arPurchaseData['returnUrl']        = url(str_replace(':slug', $obOrder->secret_key, $obPage->url));

        return $arPurchaseData;
    }
}