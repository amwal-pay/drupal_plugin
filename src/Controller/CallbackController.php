<?php

namespace Drupal\commerce_amwalpay\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_amwalpay\Helper\AmwalPay;

class CallbackController extends ControllerBase
{
    /**
     * Handles the payment gateway callback after a transaction is processed.
     *
     * This method receives transaction data from AmwalPay, verifies the integrity of 
     * the response using a secure hash, and updates the order status accordingly.
     * If the payment is approved, the user is redirected to the order success page.
     * If the payment fails, the user is redirected to the payment cancellation page.
     *
     * @param Request $request
     *   The incoming request containing transaction parameters.
     *
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     *   A render array with an error message if the order is invalid,
     *   or redirects to the appropriate checkout step based on payment status.
     */
    public function index(Request $request)
    {
        $plugin_url = \Drupal::root() . '/' . \Drupal::moduleHandler()->getModule('commerce_amwalpay')->getPath() . '/';
        $log_path = $plugin_url . 'amwalpay.log';

        // Retrieve transaction parameters from the request.
        $merchantReference = htmlspecialchars($request->query->get('merchantReference'));
        $amount = htmlspecialchars($request->query->get('amount'));
        $currencyId = htmlspecialchars($request->query->get('currencyId'));
        $customerId = htmlspecialchars($request->query->get('customerId'));
        $customerTokenId = htmlspecialchars($request->query->get('customerTokenId'));
        $responseCode = htmlspecialchars($request->query->get('responseCode'));
        $transactionId = htmlspecialchars($request->query->get('transactionId'));
        $transactionTime = htmlspecialchars($request->query->get('transactionTime'));
        $secureHashValueOld = htmlspecialchars($request->query->get('secureHashValue'));

        // Extract the order ID from the merchant reference.
        $orderId = substr($merchantReference, 0, -9);
        if (!$orderId) {
            return ['#markup' => $this->t('Order ID not found.')];
        }

        // Load the order entity.
        $order = \Drupal\commerce_order\Entity\Order::load($orderId);
        if (!$order) {
            return ['#markup' => $this->t('Invalid order ID.')];
        }

        // Ensure that the payment method is AmwalPay.
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
        $merchant_id = $configuration['merchant_id'];
        $terminal_id = $configuration['terminal_id'];
        $secret_key = $configuration['secret_key'];
        $debug = $configuration['debug'];

        $isPaymentApproved = false;

        // Create an array of transaction details for hash verification.
        $integrityParameters = [
            "amount" => $amount,
            "currencyId" => $currencyId,
            "customerId" => $customerId,
            "customerTokenId" => $customerTokenId,
            "merchantId" => $merchant_id,
            "merchantReference" => $merchantReference,
            "responseCode" => $responseCode,
            "terminalId" => $terminal_id,
            "transactionId" => $transactionId,
            "transactionTime" => $transactionTime
        ];

        // Log the callback response.
        AmwalPay::addLogs($debug, $log_path, 'Callback Response: ', print_r($integrityParameters, 1));

        // Generate a secure hash for validation.
        $secureHashValue = AmwalPay::generateStringForFilter($integrityParameters, $secret_key);
        $integrityParameters['secureHashValue'] = $secureHashValue;
        $integrityParameters['secureHashValueOld'] = $secureHashValueOld;

        // Validate the secure hash.
        if ($responseCode === "00" || $secureHashValue == $secureHashValueOld) {
            $isPaymentApproved = true;
        }

        // Log hash comparison result.
        $info = 'Old Hash -- ' . $secureHashValueOld . '  New Hash -- ' . $secureHashValue;
        AmwalPay::addLogs($debug, $log_path, $info);

        // Redirect based on payment approval status.
        if ($isPaymentApproved) {
            $note = 'AmwalPay : Payment Approved';
            $msg = 'In callback action, for order #' . $orderId . ' ' . $note;
            AmwalPay::addLogs($debug, $log_path, $msg);
            $url = Url::fromRoute('commerce_payment.checkout.return', ['commerce_order' => $orderId, 'tid' => $transactionId]);
        } else {
            $note = 'AmwalPay : Payment is not completed';
            $msg = 'In callback action, for order #' . $orderId . ' ' . $note;
            AmwalPay::addLogs($debug, $log_path, $msg);
            $url = Url::fromRoute('commerce_payment.checkout.cancel', ['commerce_order' => $orderId]);
        }

        // Set checkout step and redirect the user.
        $url->setRouteParameter('step', 'payment');
        $url->setAbsolute();
        $returnurl = $url->toString();
        header("Location: " . $returnurl);
        die;
    }
}
