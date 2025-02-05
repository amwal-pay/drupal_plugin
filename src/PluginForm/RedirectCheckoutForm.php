<?php

namespace Drupal\commerce_amwalpay\PluginForm;

use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class RedirectCheckoutForm extends BasePaymentOffsiteForm
{
  /**
   * Builds the configuration form and redirects the user to the AmwalPay SmartBox page.
   *
   * This method retrieves the order ID, constructs the redirect URL, and then 
   * redirects the user to the SmartBox execution page.
   *
   * @param array $form1
   *   The form structure.
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   A redirect form configuration.
   */
  public function buildConfigurationForm(array $form1, FormStateInterface $form_state)
  {
    // Build the parent form structure.
    $form = parent::buildConfigurationForm($form1, $form_state);
    
    // Define the log file path.
    $pluginlog = \Drupal::root() . '/' . \Drupal::moduleHandler()->getModule('commerce_amwalpay')->getPath() . '/amwalpay.log';

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $payment->getOrder();
    $orderId = $order->id();

    // Generate the URL to execute the SmartBox payment process.
    $reObj = Url::fromRoute('commerce_amwalpay.execute_smartbox', ['order_id' => $orderId], ['absolute' => TRUE]);
    $reObj->setAbsolute();

    // Redirect the user to the SmartBox payment page.
    return $this->buildRedirectForm(
      $form,
      $form_state,
      $reObj->toString(),
      [],
      self::REDIRECT_GET
    );
  }
}