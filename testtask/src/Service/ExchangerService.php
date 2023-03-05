<?php

namespace Drupal\testtask\Service;

use Drupal\Core\Database\Connection;
use GuzzleHttp\ClientInterface;
use Drupal\Component\Serialization\Json;

class ExchangerService {

  const UPDATE_PERIOD = 3600 * 24;

  const NAMES_URL = 'https://api.apilayer.com/fixer/symbols';

  protected $database;

  protected $id;

  protected $name;

  protected $url;

  protected $key;

  protected $httpClient;


  public function __construct(Connection $connection, ClientInterface $http_client) {
    $this->database = $connection;
    $this->httpClient = $http_client;
  }

  /**
   * @param int $id
   *
   * @return $this
   */
  public function setExchangerId(int $id): ExchangerService {
    $this->id = $id;
    return $this;
  }

  /**
   * @return $this
   */
  public function setup(): ExchangerService {

    $settings = $this->database->select('testtask_exchangers', 'e')
      ->condition('e.id', $this->id, '=')
      ->fields('e', ['name', 'url', 'key'])
      ->execute()
      ->fetchAssoc();

    $this->name = $settings['name'];
    $this->url = $settings['url'];
    $this->key = $settings['key'];

    return $this;
  }

  /**
   * @param int $exchangerId
   * @param int $amount
   * @param int $from
   * @param int $to
   *
   * @return array
   */
  public function exchange(int $exchangerId, int $amount, int $from, int $to): array {

    $this->checkOutdatedRates($exchangerId);

    $currencyFrom = $this->getSingleCurrency($exchangerId, $from);
    $currencyTo = $this->getSingleCurrency($exchangerId, $to);

    $result = $amount * $currencyTo['rate'] / $currencyFrom['rate'];

    return ['from' => $currencyFrom, 'to' => $currencyTo, 'result' => $result];
  }

  /**
   * @param int $exchangerId
   * @param int $currency
   *
   * @return array
   */
  private function getSingleCurrency(int $exchangerId, int $currency): array {

    return $this->database->select('testtask_currencies', 'c')
      ->condition('c.id', $currency, '=')
      ->condition('c.exchanger_id', $exchangerId, '=')
      ->fields('c', ['code', 'rate'])
      ->execute()
      ->fetchAssoc();
  }

  /**
   * @param int $exchangerId
   *
   * @return void
   */
  private function checkOutdatedRates(int $exchangerId): void {

    $lastUpdated = $this->getUpdatedTime($exchangerId);

    $latsUpdatedTimestamp = strtotime($lastUpdated['updated']);

    if (time() > ($latsUpdatedTimestamp + self::UPDATE_PERIOD)) {
      $this->setExchangerId($exchangerId)->setup()->getCurrencyRates();
    }

  }

  /**
   * @param int $exchangerId
   *
   * @return array
   */
  public function getUpdatedTime(int $exchangerId): array {

    return $this->database->select('testtask_exchangers', 'e')
      ->fields('e', ['updated'])
      ->condition('id', $exchangerId, '=')
      ->execute()
      ->fetchAssoc();
  }

  /**
   * @param int $exchangerId
   *
   * @return array
   */
  public function getRates(int $exchangerId): array {

    return $this->database->select('testtask_currencies', 'c')
      ->fields('c', ['id', 'name', 'code', 'rate', 'available'])
      ->condition('exchanger_id', $exchangerId, '=')
      ->execute()
      ->fetchAllAssoc('code', \PDO::FETCH_ASSOC);

  }

  /**
   * @param int $exchangerId
   * @param array $currencies
   *
   * @return void
   */
  public function updateAvailableCurrencies(int $exchangerId, array $currencies): void {
    $this->database->update('testtask_currencies')
      ->fields([
        'available' => '',
      ])
      ->execute();

    foreach ($currencies as $id) {

      $this->database->update('testtask_currencies')
        ->fields([
          'available' => 1,
        ])
        ->condition('exchanger_id', $exchangerId, '=')
        ->condition('id', $id, '=')
        ->execute();
    }
  }

  /**
   * @param int $exchangerId
   *
   * @return array
   */
  public function getAvailableCurrencies(int $exchangerId): array {
    return $this->database->select('testtask_currencies', 'c')
      ->fields('c', ['id', 'code'])
      ->condition('exchanger_id', $exchangerId, '=')
      ->condition('available', 1, '=')
      ->execute()
      ->fetchAllKeyed();
  }


  /**
   * @return array
   */
  public function getAllExchangers(): array {
    return $this->database->select('testtask_exchangers', 'e')
      ->fields('e', ['id', 'name'])
      ->execute()
      ->fetchAllKeyed();
  }

  /**
   * @return $this
   */
  public function getCurrencyNames(): ExchangerService {

    $response = $this->makeRequest(self::NAMES_URL);

    if ($response['success'] === TRUE) {
      $this->storeCurrencyNames($response['symbols']);
    }

    return $this;
  }

  /**
   * @return void
   */
  public function getCurrencyRates(): void {

    $response = $this->makeRequest($this->url);

    if ($response['success'] === TRUE) {
      $this->updateCurrencyRates($response);
    }
  }

  /**
   * @param string $url
   *
   * @return array
   */
  private function makeRequest(string $url): array {
    try {
      $response = $this->httpClient->request('GET', $url, [
        'allow_redirects' => [
          'max' => 10,
        ],
        'headers' => [
          'Content-Type' => 'text/plain',
          'apikey' => $this->key,
        ],
      ]);
    } catch (\Exception $e) {
      echo 'Exception: ', $e->getMessage();
    }


    return Json::decode($response->getBody());
  }

  /**
   * @param array $data
   *
   * @return void
   */
  private function storeCurrencyNames(array $data): void {

    $values = [];

    foreach ($data as $code => $name) {
      $values[] = [
        'exchanger_id' => $this->id,
        'code' => $code,
        'name' => $name,
      ];
    }

    $query = $this->database->insert('testtask_currencies')
      ->fields(['exchanger_id', 'code', 'name']);
    foreach ($values as $record) {
      $query->values($record);
    }
    $query->execute();
  }

  /**
   * @param array $data
   *
   * @return void
   */

  private function updateCurrencyRates(array $data): void {

    foreach ($data['rates'] as $code => $rate) {

      $this->database->update('testtask_currencies')
        ->fields([
          'rate' => $rate,
        ])
        ->condition('exchanger_id', $this->id, '=')
        ->condition('code', $code, '=')
        ->execute();
    }

    $this->database->update('testtask_exchangers')
      ->fields([
        'updated' => date("Y-m-d H:i:s", $data['timestamp']),
      ])
      ->condition('id', $this->id, '=')
      ->execute();
  }

}
