<?php

/**
 * Трейт WebinarSubscribers. Содержит методы для работы с подписчиками вебинаров
 *
 * @author    andrey-tech
 * @copyright 2020 andrey-tech
 * @see https://github.com/andrey-tech/bizon365-api-php
 * @license   MIT
 *
 * @version 1.0.0
 *
 * v1.0.0 (02.08.2020) Начальный релиз
 *
 */

declare(strict_types = 1);

namespace App\Bizon365;

trait WebinarSubscribers
{
    /**
     * Возвращает список страниц регистрации и их рассылок
     * @param int $skip Пропустить указанное число записей
     * @param int $limit Ограничить количество записей (не более 50)
     * @return array
     * @throws Bizon365APIException
     * @see https://blog.bizon365.ru/api/v1/webinars/subpages/
     */
    public function getWebinarSubpages(
        int $skip = 0,
        int $limit = 50
    ) :array {
        $response = $this->request(
            'webinars/subpages/getSubpages',
            'GET',
            [ 'skip' => $skip, 'limit' => $limit ]
        );

        // Проверка статуса ответа
        if (! $this->http->isSuccess()) {
            $httpCode = $this->http->getHTTPCode();
            $response = $this->http->getResponse();
            throw new Bizon365APIException(
                "Не удалось получить список страниц регистрации по вебинарам (HTTP code {$httpCode}): {$response}"
            );
        }

        if (! empty($response['errors'])) {
            $jsonErrors = $this->toJSON($response['errors']);
            throw new Bizon365APIException("Ошибки при загрузке списка страниц регистрации по вабинарам: {$jsonErrors}");
        }

        return $response['pages'];
    }

    /**
     * Возвращает список ВСЕХ страниц регистрации и их рассылок
     * @param int $skip Пропустить указанное число записей
     * @param int $limit Количество записей в одном запросе (не более 50)
     * @return array
     * @throws Bizon365APIException
     * @see https://blog.bizon365.ru/api/v1/webinars/subpages/
     */
    public function getAllWebinarSubpages(
        int $skip = 0,
        int $limit = 50
    ) :array {
        $webinarSubpages = [];

        do {
            $subpages = $this->getWebinarSubpages($skip, $limit);
            $webinarSubpages = array_merge($webinarSubpages, $subpages);
            $skip += $limit;
            $response = $this->http->getResponse(false);
        } while (! (count($webinarSubpages) == $response['total'] || count($subpages) == 0));

        return $webinarSubpages;
    }

    /**
     * Возвращает список подписчиков для заданной страницы регистрации
     * @param string $pageId ID страницы регистрации
     * @param int $skip Пропустить указанное число записей
     * @param int $limit Ограничить количество записей (не более 1000)
     * @param string $webinarTimeMin Нижняя граница для времени сеанса, на который зарегистрированы подписчики  в формате ISO8601
     * @param string $webinarTimeMax Верхняя граница для времени сеанса, на который зарегистрированы подписчики  в формате ISO8601
     * @param string $registeredTimeMin Нижняя граница для времени регистрации подписчика в формате ISO8601
     * @param string $registeredTimeMax Верхняя граница для времени регистрации подписчика в формате ISO8601
     * @param string $url_marker Значение маркера из URL, идентификатор партнера
     * @return array
     * @throws Bizon365APIException
     * @see https://blog.bizon365.ru/api/v1/webinars/subpages/
     */
    public function getWebinarSubscribers(
        string $pageId,
        int $skip = 0,
        int $limit = 1000,
        string $webinarTimeMin = null,
        string $webinarTimeMax = null,
        string $registeredTimeMin = null,
        string $registeredTimeMax = null,
        string $url_marker = null
    ) :array {

        $params = [ 'pageId' => $pageId, 'skip' => $skip, 'limit' => $limit ];
        $names = [ 'registeredTimeMin', 'registeredTimeMax', 'webinarTimeMin', 'webinarTimeMax', 'url_marker'];
        foreach ($names as $name) {
            if (isset($$name)) {
                $params[ $name ] = $$name;
            }
        }

        $response = $this->request(
            'webinars/subpages/getSubscribers',
            'GET',
            $params
        );

        if (! $this->http->isSuccess()) {
            $httpCode = $this->http->getHTTPCode();
            $response = $this->http->getResponse();
            throw new Bizon365APIException(
                "Не удалось загрузить список подписчиков вебинара для страницы {$pageId} (HTTP code {$httpCode}): {$response}"
            );
        }

        if (! empty($response['errors'])) {
            $jsonErrors = $this->toJSON($response['errors']);
            throw new Bizon365APIException("Ошибки при загрузке списка подписчиков вебинара для страницы {$pageId}: {$jsonErrors}");
        }

        return $response['list'];
    }

    /**
     * Возвращает список ВСЕХ подписчиков для заданной страницы регистрации
     * @param string $pageId ID страницы регистрации
     * @param int $skip Пропустить указанное число записей
     * @param int $limit Количество записей в одном запросе (не более 1000)
     * @param string $webinarTimeMin Нижняя граница для времени сеанса, на который зарегистрированы подписчики  в формате ISO8601
     * @param string $webinarTimeMax Верхняя граница для времени сеанса, на который зарегистрированы подписчики  в формате ISO8601
     * @param string $registeredTimeMin Нижняя граница для времени регистрации подписчика в формате ISO8601
     * @param string $registeredTimeMax Верхняя граница для времени регистрации подписчика в формате ISO8601
     * @param string $url_marker Значение маркера из URL, идентификатор партнера
     * @return array
     * @throws Bizon365APIException
     * @see https://blog.bizon365.ru/api/v1/webinars/subpages/
     */
    public function getAllWebinarSubscribers(
        string $pageId,
        int $skip = 0,
        int $limit = 1000,
        string $webinarTimeMin = null,
        string $webinarTimeMax = null,
        string $registeredTimeMin = null,
        string $registeredTimeMax = null,
        string $url_marker = null
    ) :array
    {
        $webinarSubscribers = [];

        do {
            $subscribers = $this->getWebinarSubscribers(
                $pageId,
                $skip,
                $limit,
                $webinarTimeMin,
                $webinarTimeMax,
                $registeredTimeMin,
                $registeredTimeMax,
                $url_marker
            );
            $webinarSubscribers = array_merge($webinarSubscribers, $subscribers);
            $skip += $limit;
            $response = $this->http->getResponse(false);
        } while (! (count($webinarSubscribers) == $response['total'] || count($subscribers) == 0));

        return $webinarSubscribers;
    }
}
