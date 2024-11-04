<?php

namespace Stroy\Regionality\Location;

/**
 * Класс для работы с полями
 */
class Fields
{
    /** @var array */
    protected array $values = [];

    public function __construct(array $values = null)
    {
        if ($values !== null) {
            $this->values = $values;
        }
    }

    /**
     * @bref Возвращает любую переменную по ее ключу. Null, если переменная не установлена.
     * @param string $name
     * @return string | mixed
     */
    public function get(string $name)
    {
        if (isset($this->values[$name])) {
            return $this->values[$name];
        }

        return '';
    }

    /**
     * @bref Установить любую переменную по ее ключу
     * @param string $name
     * @param mixed $value
     * @return bool
     */
    public function set(string $name, $value): bool
    {
        if ($this->markChanged($name, $value)) {
            $this->values[$name] = $value;
            return true;
        }

        return false;
    }

    /**
     * @bref Очистиь values
     * @return void
     */
    public function clear(): void
    {
        $this->values = [];
    }

    /**
     * @bref Получить все значения values
     * @return array
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @bref Получить все значения values по фильтру
     * @param array $arFilter
     * @return array
     */
    public function getFilterValues(array $arFilter): array
    {
        $arResult = [];
        foreach ($this->values as $key => $value) {
            if (in_array($key, $arFilter)) {
                $arResult[$key] = $value;
            }
        }

        return $arResult;
    }

    /**
     * @bref Установить любую переменную по ее ключу, множественная операция
     * @param array $values
     * @return void
     */
    public function setValues(array $values): void
    {
        foreach ($values as $name => $value) {
            $this->set($name, $value);
        }
    }

    /**
     * @bref Сбросить values и установить новые значения
     * @param array $values
     * @return void
     */
    public function resetValues(array $values): void
    {
        $this->values = [];
        if ($values !== null) {
            $this->values = $values;
        }
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return bool
     */
    protected function markChanged(string $name, $value): bool
    {
        $oldValue = $this->get($name);
        if ($oldValue != $value || ($oldValue === null && $value !== null)) {
            return true;
        }

        return false;
    }
}
