<?php

/**
 * Трейт WebinarViewers. Содержит методы для работы со зрителями вебинаров
 *
 * @author    andrey-tech
 * @copyright 2020-2021 andrey-tech
 * @see https://github.com/andrey-tech/bizon365-api-php
 * @license   MIT
 *
 * @version 1.0.1
 *
 * v1.0.0 (02.08.2020) Начальный релиз
 * v1.0.1 (07.02.2021) Рефакторинг
 *
 */

declare(strict_types=1);

namespace App\Bizon365;

trait WebinarViewers
{
    /**
     * Возвращает список доступных отчетов по вебинарам
     * @param int $skip Пропустить указанное число записей
     * @param int $limit Ограничить количество записей (не более 100)
     * @param bool $liveWebinars Искать среди живых вебинаров
     * @param bool $autoWebinars Искать среди автовебинаров
     * @return array
     * @throws Bizon365APIException
     * @see https://blog.bizon365.ru/api/v1/webinars/reports/
     */
    public function getWebinarList(
        int $skip = 0,
        int $limit = 100,
        bool $liveWebinars = true,
        bool $autoWebinars = true
    ): array {
        $response = $this->request(
            'webinars/reports/getlist',
            'GET',
            [
                'skip' => $skip,
                'limit' => $limit,
                'LiveWebinars' => (int) $liveWebinars,
                'AutoWebinars' => (int) $autoWebinars
            ]
        );

        // Проверка статуса ответа
        if (! $this->http->isSuccess()) {
            $httpCode = $this->http->getHTTPCode();
            $response = $this->http->getResponse();
            throw new Bizon365APIException(
                "Не удалось получить список доступных отчетов по вебинарам (HTTP code {$httpCode}): {$response}"
            );
        }

        if (! empty($response['errors'])) {
            $jsonErrors = $this->toJSON($response['errors']);
            throw new Bizon365APIException("Ошибки при загрузке доступных отчетов по вебинарам: {$jsonErrors}");
        }

        return $response['list'];
    }

    /**
     * Возвращает список ВСЕХ доступных отчетов по вебинарам
     * @param int $skip Пропустить указанное число записей
     * @param int $limit Количество записей в одном запросе (не более 100)
     * @param bool $liveWebinars Искать среди живых вебинаров
     * @param bool $autoWebinars Искать среди автовебинаров
     * @return array
     * @throws Bizon365APIException
     * @see https://blog.bizon365.ru/api/v1/webinars/reports/
     */
    public function getAllWebinarList(
        int $skip = 0,
        int $limit = 100,
        bool $liveWebinars = true,
        bool $autoWebinars = true
    ): array {
        $webinarList = [];

        do {
            $list = $this->getWebinarList($skip, $limit, $liveWebinars, $autoWebinars);
            $webinarList = array_merge($webinarList, $list);
            $skip += $limit;
            $response = $this->http->getResponse(false);
        } while (! (count($webinarList) == $response['count'] || count($list) == 0));

        return $webinarList;
    }

    /**
     * Возвращает список зрителей вебинара
     * @param string $webinarId Id вебинара
     * @param int $skip Пропустить указанное число записей
     * @param int $limit Ограничить количество записей (не более 1000).
     * @return array
     * @throws Bizon365APIException
     * @see https://blog.bizon365.ru/api/v1/webinars/reports/
     */
    public function getWebinarViewers(string $webinarId, int $skip = 0, int $limit = 1000): array
    {
        $response = $this->request(
            'webinars/reports/getviewers',
            'GET',
            [ 'webinarId' => $webinarId, 'skip' => $skip, 'limit' => $limit ]
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
            throw new Bizon365APIException("Ошибки при загрузке списка зрителей вебинара {$webinarId}: {$jsonErrors}");
        }

        return $response['viewers'];
    }

    /**
     * Возвращает список ВСЕХ зрителей вебинара
     * @param string $webinarId Id вебинара
     * @param int $skip Пропустить указанное число записей
     * @param int $limit Количество записей в одном запросе. Не более 1000.
     * @return array
     * @throws Bizon365APIException
     * @see https://blog.bizon365.ru/api/v1/webinars/reports/
     */
    public function getAllWebinarViewers(string $webinarId, int $skip = 0, int $limit = 1000): array
    {
        $webinarViewers = [];

        do {
            $viewers = $this->getWebinarViewers($webinarId, $skip, $limit);
            $webinarViewers = array_merge($webinarViewers, $viewers);
            $skip += $limit;
            $response = $this->http->getResponse(false);
        } while (! (count($webinarViewers) == $response['total'] || count($viewers) == 0));

        return $webinarViewers;
    }
}
