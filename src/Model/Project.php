<?php

namespace Portfolio\Model;

use Collection\TypedArray;
use Symfony\Component\Yaml\Yaml;
use RuntimeException;

/**
 * A single project instance
 *
 * It is primarily a container for an array of 0 or more `Activity` instances
 *
 * Example usage:
 *
 * ```php
 * $p = new Project($portfolio);
 * $p->getActivities()->add($activity1);
 * $p->getActivities()->add($activity2);
 * ```
 *
 * @access public
 */
class Project extends BaseModel
{
    /**
     * Parent portfolio instance
     * @var Portfolio
     */
    protected $portfolio;

    /**
     * Project title
     * @var string
     */
    protected $title;

    /**
     * Project description (details). Rendered as markdown.
     * @var string
     */
    protected $description;

    /**
     * Parent project (optional)
     * @var ?Project
     */
    protected $parent;
    protected $activityAdapter;
    protected $activities;
    protected $index;
    protected $priority = 3;

    public function __construct(Portfolio $portfolio)
    {
        $this->portfolio = $portfolio;
        $this->activities = new TypedArray(Activity::class);
        $this->properties = new TypedArray(Property::class);
    }

    /**
     * Return unique identifier
     *
     * Ensures uniqueness in `TypedArray` instances
     */
    public function identifier()
    {
        return $this->id;
    }

    /**
     * Instantiates a new Project from configuration data
     *
     * Factory method to quickly instantiate a new Project based on configuration data
     *
     * Example usage:
     * ```php
     * $project = Project::fromArray($portfolio, 'my-project', $config);
     * ```
     *
     * @param Portfolio $portfolio parent Portfolio instance.
     * @param string $id unique id for this project.
     * @param array $config configuration data.
     *
     * @return Project new project instance.
     */
    public static function fromArray(Portfolio $portfolio, string $id, array $config): Project
    {
        $obj = new self($portfolio);
        $obj->id = $id;
        $obj->title = $config['title'] ?? null;
        $obj->activityAdapter = $config['activities']['adapter'] ?? [];

        return $obj;
    }


    public function getPath()
    {
        return dirname($this->filename);
    }

    public function resolve()
    {
        // Test if one and only one root activity exists
        $rootActivities = 0;
        foreach ($this->activities as $activity) {
            if (!$activity->getParentId()) {
                $rootActivities++;
            }
        }
        // Not exactly 1: introducing a generated root activity for this project
        if ($rootActivities!=1) {
            $root = new Activity();
            $root->setTitle($this->getTitle());
            $root->setId($this->getId());

            foreach ($this->activities as $activity) {
                $activity->setProject($this);
                if (!$activity->getParentId()) {
                    $activity->setParentId($root->getId());
                }
            }
            $this->activities->add($root);
        }

        // Link parents and children (bi-directional)
        foreach ($this->activities as $activity) {
            $parentId = $activity->getParentId();
            if ($parentId) {
                if ($this->activities->hasKey($parentId)) {
                    $parent = $this->activities->get($parentId);
                    $activity->setParent($parent);
                    $parent->getChildren()->add($activity);
                    $activity->setChildIndex(count($parent->getChildren()));
                }
            }
        }

        // Link predecessors
        foreach ($this->activities as $activity) {
            $ids = $activity->getPredecessorIds();
            foreach ($ids as $id) {
                // print_r($id);
                if ($id) {
                    if (!$this->activities->hasKey($id)) {
                        throw new RuntimeException("Activity references unknown predecessor: " . $activity->getId() . '/' . $id);
                    }
                    $predecessor = $this->activities->get($id);
                    $activity->getPredecessors()->add($predecessor);
                    $predecessor->getSuccessors()->add($activity);
                }
            }
        }

        // Link resources
        foreach ($this->activities as $activity) {
            $ids = $activity->getResourceIds();
            foreach ($ids as $id) {
                $id=trim($id);
                // print_r($id);
                if ($id) {
                    if (!$this->getPortfolio()->getResources()->hasKey($id)) {
                        // throw new RuntimeException("Activity references unknown resource: #" . $activity->getId() . '/' . $id);
                    } else {
                        $resource = $this->getPortfolio()->getResources()->get($id);
                        $activity->getResources()->add($resource);
                        $resource->getActivities()->add($activity);
                    }
                }
            }
        }

        // Tag activity type based on child count
        foreach ($this->activities as $activity) {
            $type = 'task';
            if (count($activity->getChildren())>0) {
                $type='section';
            }
            $activity->setType($type);
        }

        // Resolve recursive values
        $rootIndex = 1; // Counter for root elements
        foreach ($this->activities as $activity) {
            $activity->setLevel($activity->resolveLevel());

            if ($activity->getType()=='section') {
                $activity->setEffort($activity->resolveEffort());
            }

            if ($activity->getLevel()==0) {
                $activity->setChildIndex($rootIndex);
                $rootIndex++;
            }
        }

        // Resolve wbsIds
        foreach ($this->activities as $activity) {
            $activity->setWbsId($activity->resolveWbsId());
        }

        // Resolve project-specific index by wbsId sorting
        // needs a temp array, because collections are not accepted as usort input?
        $res = [];
        foreach ($this->activities as $activity) {
            $res[] = $activity;
        }
        usort($res, function($a, $b)
        {
            return strnatcmp($a->getWbsId(), $b->getWbsId());
        });
        $i = 1;
        foreach ($res as $a) {
            $a->setIndex($i);
            $i++;
        }

        // Calculate priority keys
        foreach ($this->activities as $activity) {
            if ($activity->getType()=='task') {
                $projectPrio = $this->getPriority();
                $activityPrio = $activity->getPriority();
                $key = $activityPrio . '.' . $projectPrio . '.' . $this->getIndex() . '.' . $activity->getIndex();
                $activity->setPriorityKey($key);
            }
        }
    }

    public function getActivitiesByIndex()
    {
        $res=[];
        foreach ($this->activities as $activity) {
            $res[$activity->getIndex()] = $activity;
        }
        ksort($res);
        return $res;
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


    public function getActivitiesTotal()
    {
        return $this->activities;
    }

    public function getActivitiesByType(string $type)
    {
        $res = [];
        foreach ($this->activities as $activity) {
            if ($activity->getType()==$type) {
                $res[] = $activity;
            }
        }
        return $res;
    }

    public function getActivitiesTypeTask()
    {
        return $this->getActivitiesByType('task');
    }

    public function getActivitiesTypeSection()
    {
        return $this->getActivitiesByType('section');
    }

    public function getActivitiesByStatus(string $status)
    {
        $res = [];
        foreach ($this->activities as $activity) {
            if ($activity->getStatus()==$status) {
                $res[] = $activity;
            }
        }
        return $res;
    }

    public function getActivitiesStatusOpen()
    {
        return $this->getActivitiesByStatus('OPEN');
    }

    public function getActivitiesStatusClosed()
    {
        return $this->getActivitiesByStatus('CLOSED');
    }

}
