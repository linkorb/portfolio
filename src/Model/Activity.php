<?php

namespace Portfolio\Model;

use Collection\TypedArray;

class Activity extends BaseModel
{
    protected $title;
    protected $description;
    protected $effort;
    protected $level;
    protected $index;
    protected $childIndex;
    protected $wbsId;
    protected $project;
    protected $priority = null;
    protected $priorityKey;
    protected $parentId;
    protected $parent;
    protected $type;
    protected $status;
    protected $state;
    protected $children;
    protected $resourceIds = [];
    protected $resources;
    protected $predecessorIds = [];
    protected $predecessors;
    protected $successors;
    protected $allocations;
    protected $start;
    protected $end;
    protected $dueAt;

    public function __construct()
    {
        $this->properties = new TypedArray(Property::class);
        $this->children = new TypedArray(Activity::class);
        $this->resources = new TypedArray(Resource::class);
        $this->predecessors = new TypedArray(Activity::class);
        $this->successors = new TypedArray(Activity::class);
        $this->allocations = new TypedArray(Allocation::class);
    }

    public function identifier()
    {
        return $this->id;
    }

    public function resolveLevel()
    {
        if ($this->parent) {
            return $this->parent->resolveLevel() + 1;
        }

        return 0;
    }

    public function resolveWbsId()
    {
        $prefix = '';
        if ($this->parent) {
            $prefix = $this->parent->resolveWbsId().'.';
        }

        return $prefix.$this->getChildIndex();
    }

    public function resolveEffort()
    {
        $effort = 0;
        if ('task' == $this->getType()) {
            $effort = (int) $this->effort;
        }
        foreach ($this->getChildren() as $child) {
            $effort += $child->resolveEffort();
        }

        return $effort;
    }

    public function isOpen(): bool
    {
        return 'CLOSED' != $this->getStatus();
    }

    public function resolveCompletedEffort()
    {
        $completedEffort = 0;
        if ('task' == $this->getType()) {
            if ('CLOSED' == $this->getStatus()) {
                $completedEffort = (int) $this->effort;
            }
        }
        foreach ($this->getChildren() as $child) {
            $completedEffort += $child->resolveCompletedEffort();
        }

        return $completedEffort;
    }

    public function resolveCompletedEffortPercentage()
    {
        $completed = $this->resolveCompletedEffort();
        $total = $this->getEffort();
        $perc = 100;
        if ($total > 0) {
            $perc = round(100 / $total * $completed);
        }

        return $perc;
    }

    public function resolveCompletedChildren()
    {
        $completed = 0;
        if ('task' == $this->getType()) {
            if ('CLOSED' == $this->getStatus()) {
                ++$completed;
            }
        }

        foreach ($this->getChildren() as $child) {
            $completed += $child->resolveCompletedChildren();
        }

        return $completed;
    }

    public function resolveTotalChildren()
    {
        $total = 0;
        if ('task' == $this->getType()) {
            ++$total;
        }

        foreach ($this->getChildren() as $child) {
            $total += $child->resolveTotalChildren();
        }

        return $total;
    }

    public function resolveCompletedChildrenPercentage()
    {
        $completed = $this->resolveCompletedChildren();
        $total = $this->resolveTotalChildren();
        $perc = 100;
        if ($total > 0) {
            $perc = round(100 / $total * $completed);
        }

        return $perc;
    }

    public function resolveUnestimatedChildren()
    {
        $unestimated = 0;
        if ('task' == $this->getType()) {
            if (0 == $this->getEffort()) {
                if ('CLOSED' != $this->getStatus()) {
                    ++$unestimated;
                }
            }
        }

        foreach ($this->getChildren() as $child) {
            $unestimated += $child->resolveUnestimatedChildren();
        }

        return $unestimated;
    }

    public static function fromArray(array $config)
    {
        $obj = new self();
        $obj->id = $config['id'] ?? null;
        $obj->type = $config['type'] ?? null;
        $obj->title = $config['title'] ?? null;
        $obj->description = $config['description'] ?? null;
        $obj->effort = $config['effort'] ?? null;
        $obj->level = $config['level'] ?? null;
        $obj->status = $config['status'] ?? null;
        $obj->state = $config['state'] ?? null;
        $obj->priority = $config['priority'] ?? null;
        $obj->parentId = $config['parentId'] ?? null;
        if (isset($config['resourceIds'])) {
            $obj->resourceIds = $config['resourceIds'] ?? null;
        }
        if (isset($config['predecessorIds'])) {
            $obj->predecessorIds = $config['predecessorIds'];
        }

        return $obj;
    }

    public function toArray(): array
    {
        $data = [];
        $data['id'] = $this->id;
        $data['type'] = $this->type;
        $data['parentId'] = $this->parentId;
        $data['title'] = $this->title;
        $data['description'] = $this->description;
        $data['status'] = $this->status;
        $data['resourceIds'] = $this->resourceIds;
        $data['predecessorIds'] = $this->predecessorIds;
        $data['effort'] = $this->effort;
        $data['effort'] = $this->effort;

        return $data;
    }

    public function getPrimaryResource()
    {
        if (0 == count($this->resources)) {
            return null;
        }
        foreach ($this->resources as $resource) {
            return $resource;
        }
    }

    public function getEffortDuration()
    {
        return new \DateInterval('PT'.$this->getEffort().'H');
    }
}
