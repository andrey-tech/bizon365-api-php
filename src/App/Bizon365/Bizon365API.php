<?php

/**
 * Обертка для работы с REST API v1 Бизон365 с тротлингом запросов и логированием
 *
 * @author    andrey-tech
 * @copyright 2019-2020 andrey-tech
 * @see https://github.com/andrey-tech/bizon365-api-php
 * @license   MIT
 *
 * @version 2.0.0
 *
 * v1.0.0 (07.10.2019) Начальный релиз.
 * v1.1.0 (29.03.2020) Добавлено логирование в файл.
 * v2.0.0 (15.06.2020) Изменено название класса и названия методов класса.
 *
 */

declare(strict_types = 1);

namespace App\Bizon365;

class Bizon365API
{
    /**
     * URL для REST API
     */
    const URL = 'https://online.bizon365.ru/api/v1/';

    /**
     * Объект класса \App\HTTP
     * @var object
     */
    public $http;

    /**
     * Объект класса, выполняющего логирование
     * @param object
     */
    public $logger;

    /**
     * Токен авторизации
     * @var string
     */
    protected $authToken;

    /**
     * Конструктор
     * @param string $authToken Токен авторизации
     */
    public function __construct(string $authToken = null)
    {
        $this->authToken = $authToken;

        $this->http = new \App\HTTP();
        $this->http->useCookies = !isset($authToken);
    }

    /**
     * Выполняет предварительную авторизация с получением cookie
     * @param string $username Имя пользователя
     * @param string $password Пароль позльзователя
     * @return array
     * @see https://blog.bizon365.ru/api/v1/avtorizatsiya/
     */
    public function auth(string $username, string $password)
    {
        $response = $this->request(
            self::URL . 'auth/login',
            'POST',
            [ 'username' => $username, 'password' => $password ],
            [ 'Content-type: application/x-www-form-urlencoded' ]
        );

        if (! $this->http->isSuccess()) {
            $httpCode = $this->http->getHTTPCode();
            $response = $this->http->getResponse();
            throw new Bizon365APIException("Неудачная авторизация (HTTP code {$httpCode}): {$response}");
        }

        return $response;
    }

    /**
     * Выполняет выход пользователя из системы
     * @return array
     * @see https://blog.bizon365.ru/api/v1/avtorizatsiya/
     */
    public function logout()
    {
        $response = $this->request(
            self::URL . 'auth/logout',
            'POST',
            [],
            isset($this->authToken) ? [ "X-Token: {$this->authToken}" ] : []
        );

        if (! $this->http->isSuccess()) {
            $httpCode = $this->http->getHTTPCode();
            $response = $this->http->getResponse();
            throw new Bizon365APIException("Не удалось выйти из аккаунта (HTTP code {$httpCode}): {$response}");
        }

        return $response;
    }

    /**
     * Возвращает список доступных отчетов по вебинарам
     * @param int $skip Пропустить указанное число записей
     * @param int $limit Ограничить количество записей. Не более 100.
     * @param int $liveWebinars Искать среди живых вебинаров
     * @param int $autoWebinars Искать среди автовебинаров
     * @return array
     * @see https://blog.bizon365.ru/api/v1/webinars/reports/
     */
    public function getWebinarList(
        int $skip = 0,
        int $limit = 20,
        int $liveWebinars = 1,
        int $autoWebinars = 1
    ) :array {
        $response = $this->request(
            self::URL . 'webinars/reports/getlist',
            'GET',
            [ 'skip' => $skip, 'limit' => $limit, 'LiveWebinars' => $liveWebinars, 'AutoWebinars' => $autoWebinars ],
            isset($this->authToken) ? [ "X-Token: {$this->authToken}" ] : []
        );

        // Проверка статуса ответа
        if (! $this->http->isSuccess()) {
            $httpCode = $this->http->getHTTPCode();
            $response = $this->http->getResponse();
            throw new Bizon365APIException(
                "Не удалось получить доступных отчетов по вебинарам (HTTP code {$httpCode}): {$response}"
            );
        }

        return $response['list'];
    }

    /**
     * Возвращает список зрителей вебинара
     * @param  string $webinarId Id вебинара
     * @param int $skip Пропустить указанное число записей
     * @param int $limit Ограничить количество записей. Не более 1000.
     * @return array
     * @see https://blog.bizon365.ru/api/v1/webinars/reports/
     */
    public function getWebinarViewers(string $webinarId, int $skip = 0, int $limit = 1000) :array
    {
        $response = $this->request(
            self::URL . 'webinars/reports/getviewers',
            'GET',
            [ 'webinarId' => $webinarId, 'skip' => $skip, 'limit' => $limit ],
            isset($this->authToken) ? [ "X-Token: {$this->authToken}" ] : []
        );

        if (! $this->http->isSuccess()) {
            $httpCode = $this->http->getHTTPCode();
            $response = $this->http->getResponse();
            throw new Bizon365APIException(
                "Не удалось загрузить список зрителей вебинара {$webinarId} (HTTP code {$httpCode}): {$response}"
            );
        }

        if (! empty($response['errors'])) {
            $jsonErrors = $this->toJSON($response['errors']);
            throw new Bizon365APIException("Ошибки при загрузке списка зрителей вабинара {$webinarId}: {$jsonErrors}");
        }

        return $response['viewers'];
    }

    /**
     * Возвращает список ВСЕХ зрителей вебинара
     * @param  string $webinarId Id вебинара
     * @param int $skip Пропустить указанное число записей
     * @return array
     * @see https://blog.bizon365.ru/api/v1/webinars/reports/
     */
    public function getAllWebinarViewers(string $webinarId, int $skip = 0) :array
    {
        $webinarViewers = [];
        $limit = 1000;

        do {
            $viewers = $this->getWebinarViewers($webinarId, $skip, $limit);
            $webinarViewers = array_merge($webinarViewers, $viewers);
            $skip += $limit;
            $response = $this->http->getResponse(false);
        } while (! (count($webinarViewers) == $response['total'] || count($viewers) == 0));

        return $webinarViewers;
    }

    /**
     * Отправлет запрос к API и выполняет логирование
     * @param string $url URL запроса
     * @param string $type Метод запроса
     * @param array $params Парметры запроса
     * @param array $requestHeaders Заголовки запроса
     * @param array $curlOptions Дополнителльные опции для cURL
     * @return array|null
     */
    protected function request(
        string $url,
        string $type = 'GET',
        array $params = [],
        array $requestHeaders = [],
        array $curlOptions = []
    ) {
        if (isset($this->logger)) {
            $jsonParams = $this->toJSON($params);
            $this->logger->save("ЗАПРОС: {$type} {$url}" . PHP_EOL . $jsonParams, $this);
        }

        $response = $this->http->request($url, $type, $params, $requestHeaders, $curlOptions);

        if (isset($this->logger)) {
            $jsonResponse = $this->toJSON($response, true);
            $this->logger->save("ОТВЕТ: {$type} {$url}" . PHP_EOL . $jsonResponse, $this);
        }

        return $response;
    }

    /**
     * Преобразует данные в строку JSON для сохранения в лог
     * @param mixed $data Данные для преобразования
     * @return string
     */
    protected function toJSON($data) :string
    {
        $jsonParams = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR|JSON_PRETTY_PRINT);
        if ($jsonParams === false) {
            $jsonParams = print_r($data, true);
        }
        return $jsonParams;
    }
}
