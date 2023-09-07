<?php
    use Sitios\Bamboo\Classes\Event\ProcessPayment;
    use Omnipay\Omnipay;
    
    Route::get(ProcessPayment::SUCCESS_BACK_URL . '{slug}' , function ($sSecretKey) {
        $obPaymentGateway = new ProcessPayment();
        return $obPaymentGateway->processSuccessRequest($sSecretKey);
    });
    
    Route::get(ProcessPayment::FAIL_BACK_URL . '{slug}' , function ($sSecretKey) {
        $obPaymentGateway = new ProcessPayment();
        return $obPaymentGateway->processCancelRequest($sSecretKey);
    });
    
    Route::get(ProcessPayment::PENDING_BACK_URL . '{slug}' , function ($sSecretKey) {
        $obPaymentGateway = new ProcessPayment();
        return $obPaymentGateway->processPendingRequest($sSecretKey);
    });

    Route::get(ProcessPayment::NOTIFICATION_BACK_URL, function ($sSecretKey) {
        $obPaymentGateway = new ProcessPayment();
        return $obPaymentGateway->processNotificationRequest($sSecretKey);
    });