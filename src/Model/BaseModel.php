<?php

namespace Portfolio\Model;

use Boost\BoostTrait;
use Boost\Accessors\ProtectedAccessorsTrait;
use Collection\Identifiable;

class BaseModel implements Identifiable
{
    protected $id;
    protected $properties;

    use BoostTrait;
    use ProtectedAccessorsTrait;
    
    public function identifier()
    {
        return $this->id;
    }

    public function getPropertyValue(string $name)
    {
        $value = null;
        if (isset($this->properties[$name])) {
            $value = $this->properties[$name]->getValue();
        }
        return $value;
    }
}
