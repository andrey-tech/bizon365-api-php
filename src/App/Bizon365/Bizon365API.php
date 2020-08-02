<?php

/**
 * Обертка для работы с REST API v1 Бизон365 с тротлингом запросов и логированием
 *
 * @author    andrey-tech
 * @copyright 2019-2020 andrey-tech
 * @see https://github.com/andrey-tech/bizon365-api-php
 * @license   MIT
 *
 * @version 2.1.0
 *
 * v1.0.0 (07.10.2019) Начальный релиз
 * v1.1.0 (29.03.2020) Добавлено логирование в файл
 * v2.0.0 (15.06.2020) Изменено название класса и названия методов класса
 * v2.1.0 (02.08.2020) Добавлены трейты WebinarViewers, WebinarSubscribers
 *
 */

declare(strict_types = 1);

namespace App\Bizon365;

use App\AppException;
use App\HTTP;

class Bizon365API
{
    use WebinarViewers;
    use WebinarSubscribers;

    /**
     * URL REST API
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

        $this->http = new HTTP();
        $this->http->useCookies = !isset($authToken);
    }

    /**
     * Выполняет предварительную авторизация с получением cookie
     * @param string $username Имя пользователя
     * @param string $password Пароль пользователя
     * @return array
     * @throws Bizon365APIException
     * @see https://blog.bizon365.ru/api/v1/avtorizatsiya/
     */
    public function auth(string $username, string $password)
    {
        $response = $this->request(
            'auth/login',
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
     * @throws Bizon365APIException
     * @see https://blog.bizon365.ru/api/v1/avtorizatsiya/
     */
    public function logout()
    {
        $response = $this->request(
            'auth/logout',
            'POST'
        );

        if (! $this->http->isSuccess()) {
            $httpCode = $this->http->getHTTPCode();
            $response = $this->http->getResponse();
            throw new Bizon365APIException("Не удалось выйти из аккаунта (HTTP code {$httpCode}): {$response}");
        }

        return $response;
    }

    /**
     * Отправляет запрос к API и выполняет логирование
     * @param string $path Путь в URL запроса
     * @param string $type Метод запроса
     * @param array $params Параметры запроса
     * @param array $requestHeaders Заголовки запроса
     * @param array $curlOptions Дополнительные опции для cURL
     * @return array|null
     * @throws AppException
     */
    protected function request(
        string $path,
        string $type = 'GET',
        array $params = [],
        array $requestHeaders = [],
        array $curlOptions = []
    ) {
        $url = self::URL . $path;
        if (isset($this->authToken) && $path !== 'auth/login') {
            $requestHeaders[] = "X-Token: {$this->authToken}";
        }

        if (isset($this->logger)) {
            $jsonParams = $this->toJSON($params);
            $this->logger->save("ЗАПРОС: {$type} {$url}" . PHP_EOL . $jsonParams, $this);
        }

        $response = $this->http->request($url, $type, $params, $requestHeaders, $curlOptions);

        if (isset($this->logger)) {
            $jsonResponse = $this->toJSON($response);
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
