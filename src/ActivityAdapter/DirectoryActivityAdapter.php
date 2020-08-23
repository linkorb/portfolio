<?php

namespace Portfolio\ActivityAdapter;

use Portfolio\Model;
use Symfony\Component\Yaml\Yaml;

class DirectoryActivityAdapter extends AbstractActivityAdapter
{
    public function getActivities(Model\Project $project, array $config): array
    {
        $rows = [];
        // Load from combined activities in one file
        $path = $config['path'];
        $filename =  $path . '/activities.yaml';
        if (file_exists($filename)) {
            $yaml = file_get_contents($filename);
            $rows = Yaml::parse($yaml);
            foreach ($rows as $row) {
                $rows[$row['id']] = $row;
            }
        }

        // Load from sub directory of individual activity files
        $filenames = glob($path . '/activities/*.yaml');

        foreach ($filenames as $filename) {
            $yaml = file_get_contents($filename);
            $row = Yaml::parse($yaml);
            $rows[$row['id']] = $row;
        }
        $rows = $this->postProcessRows($rows);

        return $this->rowsToActivities($rows);
    }
}
