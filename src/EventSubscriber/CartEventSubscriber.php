<?php

namespace Drupal\commerce_customizations\EventSubscriber;

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
    if (strpos($event->getFormId(), 'commerce_order_item_add_to_cart_form') !== 0) {
      return;
    }

    $form = $event->getForm();

    foreach (['submit', 'quote'] as $button) {
      if (isset($form['actions'][$button])) {
        $form['actions'][$button]['#prefix'] = '<span class="CartButton CartButton--' . $button . '">';
        $form['actions'][$button]['#suffix'] = '</span>';
        $form['actions'][$button]['#attributes']['data-twig-suggestion'] = 'submit_button';
      }
    }

    if (isset($form['quantity']['widget'][0]['value'])) {
      $form['quantity']['widget'][0]['value']['#step'] = 1;
    }

    if (isset($form['purchased_entity']['widget'][0]['attributes'])) {
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
    }

    $form['#after_build'][] = 'commerce_customizations_set_triggering_element';

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

  /**
   * @inheritdoc
   */
  static function getSubscribedEvents() {
    $events = [];

    $events[HookEventDispatcherEvents::FORM_ALTER][] = ['alterAddToCartForm'];
    $events[HookEventDispatcherEvents::FORM_ALTER][] = ['alterCartForm'];

    return $events;
  }

}
