<?php

namespace Drupal\commerce_customizations\Plugin\Commerce\CheckoutFlow;

use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\MultistepDefault;
use Drupal\commerce_quote_cart\QuoteCartHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides the quote multistep checkout flow.
 *
 * @CommerceCheckoutFlow(
 *   id = "multistep_order",
 *   label = "Multistep - Order",
 * )
 */
class MultistepOrder extends MultistepDefault {

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    $steps = parent::getSteps();

    $steps = [
      'login' => $steps['login'],
      'order_information' => $steps['order_information'],
      'billing_information' => [
        'label' => $this->t('Billing information'),
        'next_label' => $this->t('Continue to billing'),
        'previous_label' => $this->t('Go back'),
        'has_sidebar' => TRUE,
      ],
      'review' => $steps['review'],
      'payment' => $steps['payment'],
      'complete' => $steps['complete']
    ];

    //$steps['order_information']['label'] = $this->t('Shipping info');
    $steps['payment']['label'] = $this->t('Place Order');

    return $steps;
  }

  /**
   * {@inheritdoc}
   */
  public function redirectToStep($step_id) {
    $this->order->set('checkout_step', $step_id);
    if ($step_id == 'complete') {
      $transition = $this->order->getState()->getWorkflow()->getTransition('place');
      $this->order->getState()->applyTransition($transition);
    }
    $this->order->save();
    $url = Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $this->order->id(),
      'step' => $step_id,
    ], $this->getUrlOptions($step_id));
    throw new NeedsRedirectException($url->toString());
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    if ($next_step_id = $this->getNextStepId($form['#step_id'])) {
      if ($next_step_id == 'complete') {
        $form_state->setRedirect('commerce_checkout.form', [
          'commerce_order' => $this->order->id(),
          'step' => $next_step_id,
        ], $this->getUrlOptions($next_step_id));
      }
    }
  }

  protected function getUrlOptions($step_id) {
    $options = [];
    if ($step_id == 'complete') {
      $type = 'quote';
      if (QuoteCartHelper::isMixedCart($this->order)) {
        $type = 'mixed';
      } elseif (QuoteCartHelper::isPurchaseCart($this->order)) {
        $type = 'order';
      }

      $options = ['query' => ['type' => $type]];
    }

    return $options;
  }

}
