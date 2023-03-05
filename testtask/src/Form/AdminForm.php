<?php

namespace Drupal\testtask\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\testtask\Service\ExchangerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class adminForm extends FormBase {

  /**
   * @var ExchangerService
   */
  private ExchangerService $exchanger;

  public function __construct(ExchangerService $exchanger) {
    $this->exchanger = $exchanger;
  }

  /**
   * @return string
   */
  public function getFormId(): string {
    return 'adminForm';
  }

  /**
   * @param ContainerInterface $container
   *
   * @return static
   */
  public static function create(ContainerInterface $container): adminForm {
    return new static(
      $container->get('testtask.exchanger')
    );
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $exchangers = $this->exchanger->getAllExchangers();
    $rates = $this->exchanger->getRates(key($exchangers));
    $updated = $this->exchanger->getUpdatedTime(key($exchangers))['updated'];

    foreach ($rates as $key => &$row) {

      $checked = '';

      if ($row['available'] == 1) {
        $checked = 'checked';
      }

      unset($row['available']);

      $rates[$key][]['data'] = [
        '#type' => 'checkbox',
        '#name' => 'active[' . $row['id'] . ']',
        '#checked' => $checked,
      ];

    }

    $form['exchanger'] = [
      '#type' => 'select',
      '#options' => $exchangers,
    ];

    $form['update_rates'] = [
      '#type' => 'checkbox',
      '#name' => 'update_rates',
      '#title' => 'Update currency rates (Last update: ' . $updated . ')',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update available currencies'),
    ];

    $form['rates_table'] = [
      '#theme' => 'table',
      '#rows' => $rates,
      '#header_columns' => 4,
    ];

    return $form;
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return void
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    \Drupal::messenger()->addMessage(t('Updating ...'));
    $exchangerId = $form_state->getValue('exchanger');
    $currencies = [];
    if (array_key_exists('active', $form_state->getUserInput())) {
      $currencies = array_keys($form_state->getUserInput()['active']);
    }

    $updateRates = $form_state->getValue('update_rates');

    $this->exchanger->updateAvailableCurrencies($exchangerId, $currencies);

    if ($updateRates) {
      $this->exchanger->setExchangerId($exchangerId)
        ->setup()
        ->getCurrencyRates();
    }

  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return void
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {

  }

}
