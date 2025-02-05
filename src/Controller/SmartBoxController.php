<?php

namespace Drupal\commerce_amwalpay\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use Drupal\commerce_amwalpay\Helper\AmwalPay;

class SmartBoxController extends ControllerBase
{
    /**
     * Generates and returns a render array for the AmwalPay SmartBox payment page.
     *
     * This method retrieves order details, validates the payment gateway, and 
     * prepares the necessary parameters for integrating the AmwalPay SmartBox.
     * It then returns a render array with data required to load the SmartBox payment UI.
     *
     * @param Request $request
     *   The request object containing order information.
     *
     * @return array
     *   A render array containing the SmartBox payment UI or an error message.
     */
    public function content(Request $request)
    {
        $plugin_url = \Drupal::root() . '/' . \Drupal::moduleHandler()->getModule('commerce_amwalpay')->getPath() . '/';
        $log_path = $plugin_url . 'amwalpay.log';

        // Retrieve the order ID from the request.
        $order_id = htmlspecialchars($request->query->get('order_id'));

        if (!$order_id) {
            return ['#markup' => $this->t('Order ID not found.')];
        }

        // Load the order entity.
        $order = \Drupal\commerce_order\Entity\Order::load($order_id);
        if (!$order) {
            return ['#markup' => $this->t('Invalid order ID.')];
        }

        // Ensure the payment method is AmwalPay.
        $payment_gateway = $order->get('payment_gateway')->entity;
        $payment_method_name = $order->get('payment_gateway')->entity->label();
        if ($payment_method_name !== 'amwalpay') {
            return ['#markup' => $this->t('Ops, you are accessing wrong data.')];
        }

        if ($payment_gateway) {
            $payment_gateway_plugin = $payment_gateway->getPlugin();
        }

        // Retrieve payment gateway configuration.
        $configuration = $payment_gateway_plugin->getConfiguration();
        $environment = $configuration['environment'];
        $merchant_id = $configuration['merchant_id'];
        $terminal_id = $configuration['terminal_id'];
        $secret_key = $configuration['secret_key'];
        $debug = $configuration['debug'];

        // Get order total price.
        $total_price = $order->getTotalPrice();
        $amount = $total_price->getNumber();

        // Get the store locale and determine the language (English or Arabic).
        $store = $order->getStore();
        $locale = $store->language()->getId();
        $locale = (strpos($locale, 'en') !== false) ? "en" : "ar";

        $datetime = date('YmdHis');
        $refNumber = $order_id . '_' . date("ymds");

        // Generate secure hash for transaction validation.
        $secure_hash = AmwalPay::generateString(
            $amount,
            512,
            $merchant_id,
            $refNumber,
            $terminal_id,
            $secret_key,
            $datetime
        );

        // Prepare transaction data for SmartBox.
        $data = [
            'AmountTrxn' => "$amount",
            'MerchantReference' => "$refNumber",
            'MID' => $merchant_id,
            'TID' => $terminal_id,
            'CurrencyId' => 512,
            'LanguageId' => $locale,
            'SecureHash' => $secure_hash,
            'TrxDateTime' => $datetime,
            'PaymentViewType' => 1,
            'RequestSource' => 'Checkout_Drupal',
            'SessionToken' => '',
        ];
        $jsonData = json_encode($data);

        // Get the correct SmartBox URL based on the environment.
        $smartBoxUrl = $this->getSmartBoxUrl($environment);

        // Generate callback URL.
        $reObj = Url::fromRoute('commerce_amwalpay.callback');
        $reObj->setAbsolute();
        $callback_url = $reObj->toString();

        // Store request data and log it.
        $data = [
            'jsonData' => $jsonData,
            'url' => $smartBoxUrl,
            'callback_url' => $callback_url,
        ];
        AmwalPay::addLogs($debug, $log_path, 'Payment Request: ', print_r($data, 1));

        // Return render array with the SmartBox payment UI.
        return [
            '#theme' => 'commerce_amwalpay_execute_smartbox',
            '#data' => $data,
        ];
    }

    /**
     * Returns the appropriate SmartBox URL based on the given environment.
     *
     * This method determines the SmartBox JavaScript file URL for production (prod),
     * user acceptance testing (uat), or system integration testing (sit) environments.
     *
     * @param string $environment
     *   The environment identifier (prod, uat, sit).
     *
     * @return string
     *   The SmartBox JavaScript file URL.
     */
    public function getSmartBoxUrl($environment)
    {
        if ($environment == "prod") {
            return "https://checkout.amwalpg.com/js/SmartBox.js?v=1.1";
        } elseif ($environment == "uat") {
            return "https://test.amwalpg.com:7443/js/SmartBox.js?v=1.1";
        } elseif ($environment == "sit") {
            return "https://test.amwalpg.com:19443/js/SmartBox.js?v=1.1";
        }
        return "";
    }
}
