<?php

/**
 * Класс DebugLogger. Сохраняет отладочную информацию в лог файл.
 *
 * @author    Andrey Nevsky <andrey_3@list.ru>
 * @copyright 2019-2020 Andrey Nevsky
 * @license   MIT
 *
 * @version 1.5.3
 *
 * v1.0.0 (23.08.2019) Начальный релиз.
 * v1.1.0 (30.08.2019) Добавлен флаг isActive.
 * v1.2.0 (30.08.2019) Добавлен параметр $object.
 * v1.3.0 (11.09.2019) Добавлен параметр $fileName.
 * v1.4.0 (04.10.2019) Добавлен параметр $header.
 * v1.4.1 (14.10.2019) Замена метода: getAbsoluteFilePath() на getAbsoluteFileName()
 * v1.4.2 (18.10.2019) В лог добавлены микросекунды и временная зона
 * v1.5.0 (22.10.2019) Добавлен метод getMemoryPeakUsage()
 * v1.5.1 (30.10.2019) Исправлен баг с микросекундами
 * v1.5.2 (16.11.2019) В лог добавлена величина разницы во времени
 * v1.5.3 (12.12.2019) Временная зона отделена от времени
 * v1.5.4 (15.03.2020) Замена '0' на '-' в deltaTime
 */

declare(strict_types = 1);

namespace App;

class DebugLogger
{
    /**
     * Флаг активности логгера
     * @var bool
     */
    public $isActive = false;

    /**
     * Путь для хранения лог файлов
     * @var string
     */
    public $logFilePath = 'protected/temp/';

    /**
     * Время последнего сохранения в микросекундах
     * @var float
     */
    protected $microtime;

    /**
     * Полный путь к лог файлу
     * @var string
     */
    protected $filePath;

    /**
     * Массив единственных объектов класса для каждого имени лог файла
     * @var array
     */
    private static $instances = [];

    /**
     * Конструктор
     */
    private function __construct(string $fileName)
    {
        $logFile = $this->logFilePath . $fileName;
        $this->filePath = $this->getAbsoluteFileName($logFile);
        if (is_null($this->filePath)) {
            throw new AppException("Can't find path to debug file: {$logFile}");
        }
    }

    /**
     * Возвращает единственный объект класса \App\DebugLogger
     * @return \App\RequestLogger
     */
    public static function instance(string $fileName = 'debug.log') :\App\DebugLogger
    {
        if (! isset(self::$instances[$fileName])) {
            self::$instances[$fileName] = new self($fileName);
        }
        return self::$instances[$fileName];
    }

    /**
     * Сохраняет отладочную информацию в файл
     * @param mixed $info Отладочная информация (строка или массив или объект)
     * @param object $object Объект класса в котором вызывается метод
     * @param text $header Опициональный текстовый заголовок
     * @return void
     */
    public function save($info, $object = null, $header = '')
    {
        // Если не активен (выключен)
        if (! $this->isActive) {
            return;
        }

        // Вычисляем время, прошедшее с последнего сохранения
        $microtime = microtime(true);
        $deltaMicrotime = isset($this->microtime) ? sprintf('%.6f', $microtime - $this->microtime) : '-';
        $this->microtime = $microtime;

        // Форматирует время запроса
        $dateTime = \DateTime::createFromFormat('U.u', sprintf('%.f', $microtime));
        $timeZone = new \DateTimeZone(date_default_timezone_get());
        $dateTime->setTimeZone($timeZone);
        $requestTime = $dateTime->format('Y-m-d H:i:s,u P') . " Δ{$deltaMicrotime} s";

        $memoryUsage = $this->getMemoryPeakUsage();

        // Создает сообщение для лог файла
        $message = "*** [{$requestTime}, {$memoryUsage}] " . str_repeat('*', 20) . PHP_EOL;

        // Формируем заголовок
        if (isset($object) && is_object($object)) {
            $className = get_class($object);
            $message .= "* Class: {$className}\n";
        }

        // Опциональный текстовый заголовок
        if (! empty($header)) {
            $message .= "* {$header}\n";
        }

        if (! is_string($info)) {
            $jsonInfo = json_encode($info, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($jsonInfo === false) {
                $errorMessage = json_last_error_msg();
                throw new AppException("Ошибка кодирования JSON ({$errorMessage}): " . print_r($info, true));
            }
            $info = $jsonInfo;
        }

        $message .= $info . PHP_EOL . PHP_EOL;

        // Записывет сообщение в лог файл
        if (! file_put_contents($this->filePath, $message, FILE_APPEND|LOCK_EX)) {
            throw new AppException("Can't save to log file: {$this->filePath}");
        }
    }

    /**
     * Возвращает абсолютное имя файла и создает каталоги при необходимости
     * @param string $relativeFileName Относительное имя файла
     * @param bool $createDir Создавать каталоги при необходимости?
     * @return string|null Абсолютное имя файла
     * @see http://php.net/manual/ru/function.stream-resolve-include-path.php#115229
     */
    private function getAbsoluteFileName(string $relativeFileName, bool $createDir = true)
    {
        $includePath = explode(PATH_SEPARATOR, get_include_path());
        foreach ($includePath as $path) {
            $absoluteFileName = $path . DIRECTORY_SEPARATOR . $relativeFileName;
            $checkDir = dirname($absoluteFileName);
            if (is_dir($checkDir)) {
                return $absoluteFileName;
            }
            if ($createDir) {
                if (! mkdir($checkDir, $mode = 0755, $recursive = true)) {
                    throw new ControllerException("Can't create dir: {$checkDir}");
                }
                return $absoluteFileName;
            }
        }
        return null;
    }

    /**
     * Возвращает строку о пиковом использовании памяти
     * @return string
     */
    protected function getMemoryPeakUsage() :string
    {
        return sprintf('%0.2f', memory_get_peak_usage(false)/1024/1024) . '/' .
            sprintf('%0.2f', memory_get_peak_usage(true)/1024/1024) . ' MiB';
    }
}
