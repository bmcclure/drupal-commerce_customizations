<?php

namespace Drupal\commerce_customizations\EventSubscriber;

use Drupal\commerce_order\Event\OrderAssignEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\hook_event_dispatcher\Event\Form\FormAlterEvent;
use Drupal\hook_event_dispatcher\HookEventDispatcherEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class CartEventSubscriber
 *
 * @package Drupal\commerce_customizations\EventSubscriber
 */
class CartEventSubscriber implements EventSubscriberInterface {

  /**
   * @param \Drupal\hook_event_dispatcher\Event\Form\FormAlterEvent $event
   */
  public function alterAddToCartForm(FormAlterEvent $event) {
    if (strpos($event->getFormId(), 'commerce_order_item_default_add_to_cart_') !== 0) {
      return;
    }

    $form = $event->getForm();

    if (!isset($form['purchased_entity']['widget'][0]['attributes'])) {
      return;
    }

    $attributes = $form['purchased_entity']['widget'][0]['attributes'];

    foreach ($attributes as $key => $element) {
      if (strpos($key, 'attribute_') !== 0) {
        continue;
      }

      if (!empty($element['#options'])) {
        if (count($element['#options']) == 1) {
          $values = array_values($element['#options']);

          if ($values[0] == 'N/A' || $values[0] == 'Standard Version') {
            $form['purchased_entity']['widget'][0]['attributes'][$key]['#access'] = FALSE;
          }
        }
      }
    }

    $event->setForm($form);
  }

  /**
   * @param \Drupal\hook_event_dispatcher\Event\Form\FormAlterEvent $event
   */
  public function alterCartForm(FormAlterEvent $event) {
    if (strpos($event->getFormId(), 'views_form_commerce_cart_form_') !== 0) {
      return;
    }

    $form = $event->getForm();

    $buttons = [
      'checkout' => 'CartCheckoutButton',
      'convert' => 'CartConvertButton',
      'submit' => 'UpdateCartButton',
    ];

    foreach ($buttons as $id => $class) {
      if (empty($form['actions'][$id])) {
        continue;
      }

      $form['actions'][$id]['#attributes']['class'][] = 'CheckoutButton-input';
      $form['actions'][$id]['#prefix'] = '<span class="' . $class . '">';
      $form['actions'][$id]['#suffix'] = '</span>';
    }

    if (isset($form['remove_button'])) {
      foreach ($form['remove_button'] as $index => $button) {
        if (!is_numeric($index)) {
          continue;
        }

        $form['remove_button'][$index]['#attributes']['class'][] = 'CartRemoveButton-input';
        $form['remove_button'][$index]['#prefix'] = '<span class="CartRemoveButton">';
        $form['remove_button'][$index]['#suffix'] = '</span>';
      }
    }

    $form['#attached']['library'][] = 'commerce_customizations/cart-form';

    $event->setForm($form);
  }

  public function onOrderAssign(OrderAssignEvent $event) {
    $order = $event->getOrder();

    // Return immediately if it's not a cart.
    if ($order->get('cart')->isEmpty() || !$order->get('cart')->value) {
      return;
    }

    /** @var \Drupal\commerce_cart\CartProviderInterface $cart_provider */
    $cart_provider = \Drupal::service('commerce_cart.cart_provider');
    $carts = $cart_provider->getCarts($event->getAccount());

    if (!empty($carts)) {
      $cart = array_shift($carts);

      if ($order->id() != $cart->id()) {
        foreach ($order->getItems() as $item) {
          $cart->addItem($item);
          $order->removeItem($item);
        }

        $cart->save();
      }
    }
  }

  /**
   * @inheritdoc
   */
  static function getSubscribedEvents() {
    $events = [];

    $events[HookEventDispatcherEvents::FORM_ALTER][] = ['alterAddToCartForm'];
    $events[HookEventDispatcherEvents::FORM_ALTER][] = ['alterCartForm'];
    $events[OrderEvents::ORDER_ASSIGN][] = ['onOrderAssign'];

    return $events;
  }

}
