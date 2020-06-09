<?php

namespace Portfolio\Model;

use Collection\TypedArray;
use Symfony\Component\Yaml\Yaml;

class Resource extends BaseModel
{
    protected $title;
    protected $image;
    protected $description;
    protected $activities;
    protected $timeslots;
    

    public function __construct()
    {
        $this->activities = new TypedArray(Activity::class);
        $this->properties = new TypedArray(Property::class);
        $this->timeslots = new TypedArray(Timeslot::class);
    }

    public function identifier()
    {
        return $this->id;
    }

    public static function fromArray(string $id, array $config)
    {
        $obj = new self();
        $obj->id = $id;
        $obj->title = $config['title'] ?? null;
        $obj->image = $config['image'] ?? null;

        return $obj;
    }

    public function getActivitiesByPriorityKey()
    {
        $res=[];
        foreach ($this->activities as $activity) {
            if ($activity->getType()=='task') {
                if ($activity->getStatus()!='CLOSED') {
                    $res[$activity->getPriorityKey()] = $activity;
                }
            }
        }
        usort($res, function($a, $b)
        {
            return strnatcmp($a->getPriorityKey(), $b->getPriorityKey());
        });
        return $res;

    }

}
