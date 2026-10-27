<?php
namespace vznpc\utils;
trait LegacyDataPropertyCompat
{
    public function getDataProperty(int $id)
    {
        return $this->propertyManager->getPropertyValue($id, -1);
    }
    public function setDataProperty(int $id, int $type, $value, bool $force = true):void
    {
        $this->propertyManager->setPropertyValue($id, $type, $value, $force);
    }
}
