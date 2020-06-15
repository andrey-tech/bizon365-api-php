<?php

/**
 * Обработчик исключений в классах пространства имен \App\Bizon365
 *
 * @author    andrey-tech
 * @copyright 2019-2020 andrey-tech
 * @see https://github.com/andrey-tech/bizon365-api-php
 * @license   MIT
 *
 * @version 1.0.0
 *
 * v1.0.0 (13.10.2019) Начальный релиз
 *
 */

declare(strict_types = 1);

namespace App\Bizon365;

class Bizon365APIException extends \Exception
{
    /**
     * Конструктор
     * @param string $message Сообщение об исключении
     * @param int $code Код исключения
     * @param \Exception|null $previous Предыдущее исключение
     */
    public function __construct(string $message = '', $code = 0, \Exception $previous = null)
    {
        parent::__construct("Bizon365API: " . $message, $code, $previous);
    }
}
