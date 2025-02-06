<?php

namespace Drupal\commerce_amwalpay\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides the AmwalPay offsite Checkout payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "commerce_amwalpay",
 *   label = @Translation("Amwal Pay"),
 *   display_label = @Translation("Amwal Pay"),
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_amwalpay\PluginForm\RedirectCheckoutForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "mastercard", "visa", "omannet"
 *   },
 * )
 */
class Checkout extends OffsitePaymentGatewayBase
{
    protected $plugin_url;
    protected $log_path;

    public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time);
        $this->plugin_url = \Drupal::root() . '/' . \Drupal::moduleHandler()->getModule('commerce_amwalpay')->getPath() . '/';
        $this->log_path = $this->plugin_url . 'amwalpay.log';
    }

    /**
     * Provides default configuration values for the payment gateway.
     */
    public function defaultConfiguration()
    {
        return [
            'label' => 'amwalpay',
            'environment' => 'uat',
            'merchant_id' => '',
            'terminal_id' => '',
            'secret_key' => '',
            'debug' => '1'
        ] + parent::defaultConfiguration();
    }

    /**
     * Builds the configuration form for the payment gateway settings.
     */
    public function buildConfigurationForm(array $form1, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form1, $form_state);

        $form['environment'] = [
            '#type' => 'select',
            '#title' => $this->t("Environment"),
            '#options' => [
                'prod' => $this->t('Production'),
                'sit' => $this->t('SIT'),
                'uat' => $this->t('UAT'),
            ],
            '#description' => $this->t('Select the environment for Amwal PayForm Gateway'),
            '#default_value' => $this->configuration['environment'],
            '#required' => true,
        ];
        $form['merchant_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Merchant Id'),
            '#default_value' => $this->configuration['merchant_id'],
            '#required' => true,
        ];
        $form['terminal_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Terminal Id'),
            '#default_value' => $this->configuration['terminal_id'],
            '#required' => true,
        ];
        $form['secret_key'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Secret Key'),
            '#default_value' => $this->configuration['secret_key'],
            '#required' => true,
        ];
        $form['debug'] = [
            '#type' => 'select',
            '#title' => $this->t('Debug Log'),
            '#description' => $this->t('Log file will be saved in ' . $this->plugin_url),
            '#options' => [
                '1' => $this->t('Yes'),
                '0' => $this->t('No'),
            ],
            '#default_value' => $this->configuration['debug'],
        ];
        $form['mode'] = [
            '#type' => 'value',
            '#value' => 'n/a',
        ];
        $form['mode']['#access'] = FALSE;
        return $form;
    }

    /**
     * Validates the configuration form inputs (currently disabled).
     */
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        return false;
    }

    /**
     * Saves configuration settings after form submission.
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form, $form_state);
        if (!$form_state->getErrors()) {
            $values = $form_state->getValue($form['#parents']);
            $this->configuration['environment'] = $values['environment'];
            $this->configuration['merchant_id'] = $values['merchant_id'];
            $this->configuration['terminal_id'] = $values['terminal_id'];
            $this->configuration['secret_key'] = $values['secret_key'];
            $this->configuration['debug'] = $values['debug'];
        }
    }

    /**
     * Handles payment cancellation and displays an error message.
     *
     * @param OrderInterface $order
     * @param Request $request
     */
    public function onCancel(OrderInterface $order, Request $request)
    {
        $note = 'AmwalPay : Payment is not completed';
        $this->messenger()->addError($this->t($note, ['@gateway' => $this->getDisplayLabel()]));
    }

    /**
     * Handles the return from the payment gateway and records the payment.
     *
     * @param OrderInterface $order
     * @param Request $request
     */
    public function onReturn(OrderInterface $order, Request $request)
    {
        $tid = htmlspecialchars($request->query->get('tid'));
        $note = 'AmwalPay : Payment Approved';
        $this->createPayment($order, $tid, $note, 'completed');
        $this->messenger()->addStatus($note);
    }

    /**
     * Creates a payment record in Drupal Commerce.
     *
     * @param OrderInterface $order
     * @param string $t_id
     * @param string $tstatus
     * @param string $state
     */
    function createPayment(OrderInterface $order, $t_id, $tstatus, $state)
    {
        /** @var \Drupal\commerce_payment\PaymentStorageInterface $paymentStorage */
        $paymentStorage = $this->entityTypeManager->getStorage('commerce_payment');
        /** @var PaymentInterface $payment */
        $payment = $paymentStorage->create([
            'state' => $state,
            'amount' => $order->getTotalPrice(),
            'payment_gateway' => $this->entityId,
            'order_id' => $order->id(),
            'remote_id' => $t_id,
            'remote_state' => $tstatus,
        ]);
        $payment->save();
    }
}
