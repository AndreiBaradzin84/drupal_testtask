<?php

namespace Drupal\testtask\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\testtask\Service\ExchangerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class exchangeForm extends FormBase {


  /**
   * @var ExchangerService
   */
  private ExchangerService $exchanger;

  public function __construct(ExchangerService $exchanger) {
    $this->exchanger = $exchanger;
  }

  /**
   * @param ContainerInterface $container
   *
   * @return exchangeForm
   */
  public static function create(ContainerInterface $container): exchangeForm {
    return new static(
      $container->get('testtask.exchanger')
    );
  }

  /**
   * @return string
   */
  public function getFormId(): string {
    return 'exchangeForm';
  }

  /**
   * @param array $form
   * @param FormStateInterface $form_state
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $exchangers = $this->exchanger->getAllExchangers();
    $currencies = $this->exchanger->getAvailableCurrencies(key($exchangers));


    $form['exchanger'] = [
      '#type' => 'select',
      '#options' => $exchangers,
    ];
    $form['amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Amount'),
      '#description' => $this->t('Amount to change'),
      '#required' => TRUE,
    ];
    $form['select_from'] = [
      '#type' => 'select',
      '#title' => $this->t('Select currency to sell'),
      '#options' => $currencies,
      '#required' => TRUE,
    ];
    $form['select_to'] = [
      '#type' => 'select',
      '#title' => $this->t('Select currency to buy'),
      '#options' => $currencies,
      '#required' => TRUE,
    ];
    $form['result'] = [
      '#type' => 'label',
      '#value' => 'dadsadasdasdas',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Exchange'),
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
    \Drupal::messenger()->addMessage(t('Exchanging ...'));
    $exchanger = $form_state->getValue('exchanger');
    $amount = $form_state->getValue('amount');
    $from = $form_state->getValue('select_from');
    $to = $form_state->getValue('select_to');
    if ($from === $to) {
      \Drupal::messenger()
        ->addError(t('Currency FROM should be different from currency TO'));
      return;
    }

    $result = $this->exchanger->setExchangerId($exchanger)
      ->exchange($exchanger, $amount, $from, $to);
    \Drupal::messenger()
      ->addMessage(t('You have just exchanged ' . $amount . $result['from']['code'] .
        ' to ' . $result['result'] . $result['to']['code']));
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
