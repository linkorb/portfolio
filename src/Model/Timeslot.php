<?php

namespace Portfolio\Model;

use Collection\TypedArray;

class Timeslot extends BaseModel
{
    protected $start;
    protected $end;
    protected $allocations;

    public function __construct()
    {
        $this->allocations = new TypedArray(Allocation::class);
    }

    public function getDuration()
    {
        $diff = $this->start->diff($this->end);
        return $diff;
    }

    public function getAvailableStart()
    {
        $start = $this->start;
        foreach ($this->allocations as $allocation) {
            if ($allocation->getEnd()>$start) {
                $start = $allocation->getEnd();
            }
        }
        return $start;
    }

    public function getAvailableDuration()
    {
        $start = $this->getAvailableStart();
        return $start->diff($this->end);
    }
}
