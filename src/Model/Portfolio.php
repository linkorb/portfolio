<?php

namespace Portfolio\Model;

use Portfolio\ActivityAdapter\DirectoryActivityAdapter;
use Collection\TypedArray;
use Symfony\Component\Yaml\Yaml;
use RuntimeException;

class Portfolio extends BaseModel
{
    protected $title;
    protected $description;
    protected $path;
    protected $config;
    protected $projects;
    protected $resources;

    public function __construct()
    {
        $this->projects = new TypedArray(Project::class);
        $this->resources = new TypedArray(Resource::class);
        $this->properties = new TypedArray(Property::class);
    }

    public function identifier()
    {
        return $this->fullName;
    }

    public static function fromEnv(): self
    {
        $portfolioPath = getenv('PORTFOLIO_PATH');
        $obj = Portfolio::fromPath($portfolioPath);
        return $obj;
    }

    public static function fromPath(string $path): self
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Portfolio path not found: " . $path);
        }


        // Support loading from any yaml file in the portfolio base path
        // This way part of those files can be generated, while others are manually authored
        $config = [];
        foreach (glob($path . '/*.yaml') as $filename) {
            $yaml = file_get_contents($filename);
            $newConfig = Yaml::parse($yaml);
            $config = array_merge_recursive($config, $newConfig);
        }


        $obj = new self();
        $obj->id = $config['id'] ?? null;
        $obj->title = $config['title'] ?? null;
        $obj->description = $config['description'] ?? null;
        $obj->path = $path;

        foreach ($config['resources'] as $resourceId => $resourceConfig) {
            $resource = Resource::fromArray($resourceId, $resourceConfig);
            $obj->resources->add($resource);
        }

        foreach ($config['projects'] as $projectId => $projectConfig) {
            $project = Project::fromArray($obj, $projectId, $projectConfig);
            $obj->projects->add($project);
        }

        foreach ($config['resources'] as $resourceId => $resourceConfig) {
            $resource = $obj->resources->get($resourceId);
            $timeslots = $obj->generateResourceTimeslots($resource, $resourceConfig['timeslots'] ?? []);
            foreach ($timeslots as $timeslot) {
                $resource->getTimeslots()->add($timeslot);
            }
        }

        return $obj;
    }

    public function generateResourceTimeslots(Resource $resource, array $rows)
    {
        $res = [];
        foreach ($rows as $key => $row) {
            $timezone    = 'Europe/Amsterdam';
            $startDate   = new \DateTime($row['start'], new \DateTimeZone($timezone));
            $endDate     = new \DateTime($row['end'], new \DateTimeZone($timezone)); // Optional
            $rule = (new \Recurr\Rule)
                ->setStartDate($startDate)
                ->setUntil($endDate)
                ->setTimezone($timezone)
                ->setFreq($row['frequency'])
                ->setByDay($row['days'])
            ;
            $transformer = new \Recurr\Transformer\ArrayTransformer();
            $items = $transformer->transform($rule);
            foreach ($items as $item) {
                $timeslot = new Timeslot();
                $timeslot->setId($resource->getId() . '-' . $item->getStart()->format('Ymd') . '-' . $key);
                $timeslot->setStart($item->getStart());
                $end = clone $item->getStart();
                $end->add(new \DateInterval($row['duration']));
                $timeslot->setEnd($end);
                $res[$timeslot->getId()] = $timeslot;
            }
        }
        uksort($res, "strnatcmp");
        return $res;
    }


    public function getPath()
    {
        return $this->path;
    }

    public function resolve()
    {
        $i = 1;
        foreach ($this->projects as $project) {
            $project->setIndex($i);
            $project->resolve();
            $i++;
        }
    }

    public function loadActivities()
    {
        $adapter = new DirectoryActivityAdapter();
        foreach ($this->projects as $project) {
            $path = $this->getPath() . '/projects/' . $project->getId();
            $config = [
                'path' => $path,
            ];
            $activities = $adapter->getActivities($project, $config);
            foreach ($activities as $activity) {
                $project->getActivities()->add($activity);
            }
        }
    }

    public function getActivitiesByPriorityKey()
    {
        $res=[];
        foreach ($this->projects as $project) {
            foreach ($project->getActivities() as $activity) {
                if ($activity->getType()=='task') {
                    if ($activity->getStatus()!='CLOSED') {
                        $res[$activity->getPriorityKey()] = $activity;
                    }
                }
            }
        }
        usort($res, function($a, $b)
        {
            return strnatcmp($a->getPriorityKey(), $b->getPriorityKey());
        });
        return $res;
    }

    public function schedule()
    {
        foreach ($this->getActivitiesByPriorityKey() as $activity) {
            $duration = $activity->getEffort();
            // echo $activity->getTitle() . PHP_EOL;
            $this->allocate($activity);
        }
    }

    private function getTotalMinutes(\DateInterval $int){
        return ($int->d * 24 * 60) + ($int->h * 60) + $int->i;
    }

    public function allocate(Activity $activity)
    {
        $resource = $activity->getPrimaryResource();
        if (!$resource) {
            return null;
        }

        $effortRemaining = $this->getTotalMinutes($activity->getEffortDuration());
        $minStart = null;
        $timeslots = $resource->getTimeslots();
        foreach ($timeslots as $timeslot) {
            $availableMinutes = $this->getTotalMinutes($timeslot->getAvailableDuration());
            if ($availableMinutes>0) {
                $allocationMinutes = $availableMinutes;
                if ($allocationMinutes>$effortRemaining) {
                    $allocationMinutes = $effortRemaining;
                }
                $effortRemaining -= $allocationMinutes;

                $allocation = new Allocation();
                $allocation->setTimeslot($timeslot);
                $allocation->setActivity($activity);
                $allocation->setStart($timeslot->getAvailableStart());
                $end = clone $timeslot->getAvailableStart();
                // $end->add($activity->getEffortDuration());
                $end->add(new \DateInterval('PT' . $allocationMinutes . 'M'));
                $allocation->setEnd($end);
                $timeslot->getAllocations()->add($allocation);
                $activity->getAllocations()->add($allocation);

                // Update total timespan
                if (!$activity->getStart()) {
                    $activity->setStart($allocation->getStart());
                }
                $activity->setEnd($end);
            }
            if ($effortRemaining<=0) {
                return true;
            }
        }
    }
}
