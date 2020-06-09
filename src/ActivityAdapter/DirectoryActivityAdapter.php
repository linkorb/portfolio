<?php

namespace Portfolio\ActivityAdapter;

use Portfolio\Model;
use Symfony\Component\Yaml\Yaml;

class DirectoryActivityAdapter extends AbstractActivityAdapter
{
    public function getActivities(Model\Project $project, array $config): array
    {
        $path = $config['path'];
        $filenames = glob($path . '/*.yaml');
        $rows = [];
        foreach ($filenames as $filename) {
            $yaml = file_get_contents($filename);
            $row = Yaml::parse($yaml);
            $rows[$row['id']] = $row;
        }
        // print_r($rows); exit();
        $rows = $this->postProcessRows($rows);

        return $this->rowsToActivities($rows);
    }
}