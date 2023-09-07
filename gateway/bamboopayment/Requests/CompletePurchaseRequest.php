<?php
    namespace Omnipay\BambooPayment\Requests;

    use Omnipay\Common\Message\AbstractResponse;
    use Omnipay\Common\Message\ResponseInterface;
    use Sitios\Bamboo\Classes\Helpers\GatewayHelper;

    class CompletePurchaseRequest extends AbstractResponse {
        
        public function isSuccessful() {
            return isset($this->data->init_point) && $this->data->init_point;
        }

        /**
         * Redirect for the Payment URL
         * @return boolean
         */
        public function isRedirect()
        {
            return true;
        }

        /**
         * Automatically perform any required redirect
         *
         * This method is meant to be a helper for simple scenarios. If you want to customize the
         * redirection page, just call the getRedirectUrl() and getRedirectData() methods directly.
         *
         * @return void
         */
        public function redirect()
        {
            $this->getRedirectResponse()->send();
        }

        public function getRedirectMethod()
        {
            return 'POST';
        }

        public function getRedirectData()
        {
            return [];
        }

        public function getRedirectUrl()
        {
            if ($this->isRedirect() && ( $this->data->sandbox_init_point || $this->data->init_point ) ) {
                return $this->getRequest()->getTestMode() ? $this->data->sandbox_init_point : $this->data->init_point;
            }else{
                throw new \ValidationException(['Gateway Url' => 'Gateway redirect url is not found!']);
            }
        }

        /**
         * @return HttpRedirectResponse|HttpResponse
         */
        public function getRedirectResponse()
        {
            $this->validateRedirect();

            if ('GET' === $this->getRedirectMethod()) {
                return new HttpRedirectResponse($this->getRedirectUrl());
            }

            $hiddenFields = '';
            foreach ($this->getRedirectData() as $key => $value) {
                $hiddenFields .= sprintf(
                    '<input type="hidden" name="%1$s" value="%2$s" />',
                    htmlentities($key, ENT_QUOTES, 'UTF-8', false),
                    htmlentities($value, ENT_QUOTES, 'UTF-8', false)
                )."\n";
            }

            $output = '<!DOCTYPE html>
                    <html>
                    <head>
                        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                        <title>Redirecting...</title>
                    </head>
                    <body onload="document.forms[0].submit();">
                        <form action="%1$s" method="post">
                            <p>Redirecting to payment page...</p>
                            <p>
                                %2$s
                                <input type="submit" value="Continue" />
                            </p>
                        </form>
                    </body>
                    </html>';
            $output = sprintf(
                $output,
                htmlentities($this->getRedirectUrl(), ENT_QUOTES, 'UTF-8', false),
                $hiddenFields
            );

            return new HttpResponse($output);
        }
    }