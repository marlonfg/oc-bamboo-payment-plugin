<?php
    namespace Omnipay\BambooPayment\Requests;

    use Omnipay\Common\Message\AbstractRequest;
    use Omnipay\BambooPayment\Requests\CompletePurchaseRequest;
    use Sitios\Bamboo\Classes\Helpers\GatewayHelper;

    class PurchaseRequest extends AbstractRequest {

        //testing API URL bamboo payment
        const API_BAMBOOPAYMENT_TEST = "https://api.stage.bamboopayment.com/";
        //production API URL bamboo payment
        const API_BAMBOOPAYMENT = "https://api.bamboopayment.com/";
        
        /**
         * The HTTP request
         */
        public function sendData($data) {
            //seleccionar url de acuerdo al ambiente
            $url = self::API_BAMBOOPAYMENT_TEST . '/v1/api/purchase';
            if (!$this->getTestMode())
                $url = self::API_BAMBOOPAYMENT . '/v1/api/purchase';

            $httpResponse = $this->httpClient->request(
                'POST',
                $url,
                array(
                    'Content-type'  => 'application/json',
                    'cache-control' => 'no-cache',
                    'Authorization' => 'Basic' . $this->getPrivateAccountKey(),
                ),
                GatewayHelper::toJSON($data)
            );

            $response = $httpResponse->getBody()->getContents();

            // complete request
            return $this->createResponse(json_decode($response));
        }

        /**
         * The data
         */
        public function getData() {

            if (!$this->parameters)
                return;

            $purchaseObject = [
                'items'              => [$this->prepareItems()],                
                'back_urls'          => [
                    "failure"        => url("/bamboo/order/fail") . '/' . $this->getTransactionId(),
                    "pending"        => url("/bamboo/order/pending") . '/' . $this->getTransactionId(),
                    "success"        => url("/bamboo/order/success") . '/' . $this->getTransactionId(),
                ]
            ];
            
            $this->validateToken();

            return $purchaseObject;
        }

        /**
         * Validating Token: if no production token then use testToken
         */
        function validateToken() {
            if ($this->getTestMode()) {
                $this->setToken($this->getTestAccessToken());
            }
        }

        /**
         * Preparing item info for gateway 
         */
        function prepareItems() {
            return [
                "title" => config('app.name') . " - Orden No. " . $this->getParameter('description'),
                "quantity" => 1,
                "unit_price" => (double) $this->getAmount()
            ];
        }

        /**
         * Complete Request
         */
        protected function createResponse($data)
        {
            return $this->response = new CompletePurchaseRequest($this, $data);
        }

        /**
         * Getters
         */
        public function getTestAccessToken()
        {
            return $this->getParameter('test_access_token');
        }

        /**
         * Setters
         */
        public function setTestAccessToken($value)
        {
            return $this->setParameter('test_access_token', $value);
        }

        public function getPrivateAccountKey()
        {
            return $this->getParameter('private_account_key');
        }

        public function setPrivateAccountKey($value)
        {
            return $this->setParameter('private_account_key', $value);
        }
    }
?>