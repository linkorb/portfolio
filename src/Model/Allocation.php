<?php

namespace Portfolio\Model;

use Collection\TypedArray;

class Allocation extends BaseModel
{
    protected $start;
    protected $end;
    protected $timeslot;
    protected $activity;
    
    public function getDuration()
    {
        $diff = $this->start->diff($this->end);
        return $diff;
    }

}
