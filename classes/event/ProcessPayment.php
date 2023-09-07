<?php namespace Sitios\Bamboo\Classes\Event;

use Event;
use Redirect;
use Validator;
use Omnipay\Omnipay;
use Cms\Classes\Page;
use Omnipay\Common\CreditCard;
use October\Rain\Router\Router;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Http;

use Lovata\OrdersShopaholic\Models\Status;
use Lovata\OrdersShopaholic\Classes\Helper\AbstractPaymentGateway;

class ProcessPayment extends AbstractPaymentGateway
{
    const SUCCESS_BACK_URL  = '/bamboo/order/success/';
    const FAIL_BACK_URL     = '/bamboo/order/fail/';
    const PENDING_BACK_URL  = '/bamboo/order/pending/';
    const NOTIFICATION_BACK_URL  = '/bamboo/order/notification/';

    const EVENT_PROCESS_RETURN_URL = 'shopaholic.payment_method.omnipay.gateway.process_return_url';
    const EVENT_PROCESS_CANCEL_URL = 'shopaholic.payment_method.omnipay.gateway.process_cancel_url';
    const EVENT_PROCESS_NOTIFY_URL = 'shopaholic.payment_method.omnipay.gateway.process_notify_url';

    /**
     * Get response array
     * @return string
     */
    public function getResponse() : array {
        return [];
    }

    /**
     * Get return URL
     * @return \Illuminate\Contracts\Routing\UrlGenerator|string
     */
    public function getRedirectURL() : string
    {
        if (empty($this->obPaymentMethod->gateway_property['returnUrl'])) {
            throw new \ValidationException(['returnUrl' => 'Sorry, returnUrl is empty! Please fill it up!!']);
        }

        $obPage = Page::find($this->obPaymentMethod->gateway_property['returnUrl']);

        if (str_contains($this->obPaymentMethod->gateway_property['returnUrl'], ':slug')) {
            return url(str_replace(':slug', $obOrder->secret_key, $obPage->url));
        }

        return (string) $obPage->url;
    }

    /**
     * Get cancel URL
     * @return \Illuminate\Contracts\Routing\UrlGenerator|string
     */
    protected function getCancelURL()
    {
        //Not implemented yet
        return $this->getRedirectURLForPaymentGateway(self::EVENT_GET_PAYMENT_GATEWAY_CANCEL_URL);
    }

    /**
     * Get response message
     * @return string
     */
    public function getMessage() : string {
        if (empty($this->obResponse)) {
            return (string) $this->sResponseMessage;
        }

        return (string) $this->obResponse->getMessage();
    }

    /**
     * Prepare purchase data
     */
    protected function preparePurchaseData()
    {
        if (empty($this->obOrder) || empty($this->obPaymentMethod) || empty($this->obPaymentMethod->gateway_id)) {
            return;
        }

        $this->obGateway = Omnipay::create($this->obPaymentMethod->gateway_id);

        $currencies = \Lovata\Shopaholic\Models\Currency::get()->count();

        if($this->obOrder->getTotalPrecioPorMayorValue() > 0)
            $amount =  number_format($this->obOrder->getTotalPrecioPorMayorValue(), 2, '.', '');
        elseif($currencies > 1 && $this->obOrder->getPositionPriceTotalPrecioMultimoneda() > 0)
            $amount = number_format($this->obOrder->getTotalAmountOrder(), 2, '.', '');
        else
            $amount = $this->obOrder->total_price_data->price_with_tax_value;

        $this->arPurchaseData = [
            'CardData'      => $this->getCreditCardObject(),
            'Order'         => config('app.name') . " - Orden No. " . $this->obOrder->order_number,
            'Amount'        => $amount,
            'Currency'      => $this->obPaymentMethod->gateway_currency,
            'TrxToken'         => $this->obOrder->payment_token,            
            'returnUrl'     => url(self::SUCCESS_BACK_URL.$this->obOrder->secret_key),
            'cancelUrl'     => url(self::FAIL_BACK_URL.$this->obOrder->secret_key),
            'pendingUrl'     => url(self::PENDING_BACK_URL.$this->obOrder->secret_key),
            'notificationUrl'   => url(self::NOTIFICATION_BACK_URL.$this->obOrder->secret_key),
        ];

        //Get default property list for gateway
        $arPropertyList = $this->obGateway->getDefaultParameters();
        if (empty($arPropertyList)) {
            return;
        }

        foreach ($arPropertyList as $sFieldName => $sValue) {
            $this->arPurchaseData[$sFieldName] = $this->getGatewayProperty($sFieldName);
        }

        $this->extendPurchaseData();
    }

    /**
     * Validate purchase data
     * @return bool
     */
    protected function validatePurchaseData()
    {
        $arRuleSet = [
            'Amount'   => 'required',
            'Currency' => 'required',
        ];

        $obValidator = Validator::make($this->arPurchaseData, $arRuleSet);
        if ($obValidator->fails()) {
            $this->sResponseMessage = $obValidator->messages()->first();
            return false;
        }

        return true;
    }

    /**
     * Send purchase request to payment gateway
     */
    protected function sendPurchaseData()
    {
        $arPaymentData = (array) $this->obOrder->payment_data;
        $arPaymentData['request'] = $this->arPurchaseData;
        $arPaymentData['request']['card'] = $this->arCardData;

        $this->obOrder->payment_data = $arPaymentData;
        $this->obOrder->save();

        try {
            $this->obResponse = $this->obGateway->purchase($this->arPurchaseData)->send();
        } catch (\Exception $obException) {
            $this->sResponseMessage = $obException->getMessage();
            return;
        }
    }

    /**
     * Send completePurchase request to payment gateway
     */
    protected function sendCompletePurchaseData()
    {
        try {
            $this->obResponse = $this->obGateway->completePurchase($this->arPurchaseData)->send();
        } catch (\Exception $obException) {
            $this->sResponseMessage = $obException->getMessage();
            return;
        }
    }

    /**
     * Process purchase request to payment gateway
     */
    protected function processPurchaseResponse()
    {
        if (empty($this->obResponse)) {
            return;
        }

        $this->bIsRedirect = $this->obResponse->isRedirect();
        $this->bIsSuccessful = $this->obResponse->isSuccessful();
        if ($this->bIsSuccessful && !$this->bIsRedirect) {
            $this->setSuccessStatus();
        } elseif ($this->bIsRedirect) {
            $this->setWaitPaymentStatus();
            $arPaymentResponse['redirect_url'] = $this->obResponse->getRedirectUrl();
        }

        $arPaymentResponse = (array) $this->obOrder->payment_response;
        $arPaymentResponse['response'] = (array) $this->obResponse->getData();

        $this->obOrder->payment_response = $arPaymentResponse;
        $this->obOrder->payment_token = $this->obResponse->getTransactionReference();
        $this->obOrder->transaction_id = $this->obResponse->getTransactionId();
        $this->obOrder->save();
    }

    /**
     * Process success request
     * @param string $sSecretKey
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processSuccessRequest($sSecretKey)
    {
        $this->initOrderObject($sSecretKey);
        if (empty($this->obOrder) || empty($this->obPaymentMethod)) {
            return Redirect::to('/');
        }

        //Set success status in order
        $this->setSuccessStatus();

        Event::fire(self::EVENT_PROCESS_RETURN_URL, [
            $this->obOrder,
            $this->obPaymentMethod,
        ]);

        //Get redirect URL
        $sRedirectURL = $this->getRedirectURL();

        return Redirect::to($sRedirectURL);
    }

    /**
     * Process cancel request
     * @param string $sSecretKey
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processCancelRequest($sSecretKey)
    {
        //Init order object
        $this->initOrderObject($sSecretKey);
        if (empty($this->obOrder) || empty($this->obPaymentMethod)) {
            return Redirect::to('/');
        }

        //Set cancel status in order
        $this->setCancelStatus();

        //Fire event
        Event::fire(self::EVENT_PROCESS_CANCEL_URL, [
            $this->obOrder,
            $this->obPaymentMethod,
        ]);

        //Get redirect URL
        $sRedirectURL = $this->getRedirectURL();

        return Redirect::to($sRedirectURL);
    }

    /**
     * Process pending request
     * @param string $sSecretKey
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processPendingRequest($sSecretKey)
    {
        $this->initOrderObject($sSecretKey);
        if (empty($this->obOrder) || empty($this->obPaymentMethod)) {
            return Redirect::to('/');
        }

        //Set pending status to the order
        $this->setPendingPaymentStatus();

        Event::fire(self::EVENT_PROCESS_RETURN_URL, [
            $this->obOrder,
            $this->obPaymentMethod,
        ]);

        //Get redirect URL
        $sRedirectURL = $this->getRedirectURL();

        return Redirect::to($sRedirectURL);
    }

    /**
     * Set "IN_PROGRESS" status
     */
    protected function setPendingPaymentStatus()
    {
        //Getting "IN_PROGRESS" status
        $obStatus = Status::getFirstByCode(Status::STATUS_IN_PROGRESS);

        if (empty($obStatus)) {
            return;
        }

        $this->obOrder->status_id = $obStatus->id;
        $this->obOrder->save();
    }

    /**
     * Process notification request from gateway
     * @param string $sSecretKey
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processNotificationRequest($sSecretKey)
    {
        /* if (isset($_POST) && isset($_POST["type"])) {
            switch($_POST["type"]) {
                case "payment":
                    $this->processPaymentType($sSecretKey);
                    break;
                case "test":
                    $this->processPaymentType($sSecretKey);
                    break;
                default:
                    return response()->json([
                        'status' => 'OK'
                    ]);
            }
        } */
    }

    /* protected function processPaymentType($sSecretKey) {

        $this->initOrderObject($sSecretKey);
        if (empty($this->obOrder) || empty($this->obPaymentMethod)) {
            return ;
        }

        $endpoint = 'https://api.BambooPayment.com/v1/payments/';

        $payment_id = $_POST["data"]["id"];
        $access_token = $this->getGatewayProperty('access_token');

        //Getting payment info from gateway
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $access_token,
            'X-Second' => 'bar'
        ])->get($endpoint . $payment_id);

        switch ($response->json('status')) {
            case 'approved':
                $this->setSuccessStatus();
                break;
            case 'cancelled':
                $this->setCancelStatus();
                break;
        }

        return response()->json([
            'status' => 'OK'
        ]);
    } */
}