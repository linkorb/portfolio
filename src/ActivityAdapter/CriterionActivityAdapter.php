<?php

namespace Portfolio\ActivityAdapter;

use Portfolio\Model;
use Symfony\Component\Yaml\Yaml;

class CriterionActivityAdapter extends AbstractActivityAdapter
{
    public function getActivities(Model\Project $project, array $config): array
    {
        $filename = $config['filename'];
        $yaml = file_get_contents($filename);
        $data = Yaml::parse($yaml);
        foreach ($data['items'] as $item) {
            $row = [
                'id' => $item['id'],
                'title' => $item['title'],
                'parentId' => $item['parent'] ?? null,
                'effort' => ($item['metadata']['effort'] ?? null) . 'h',
                'resourceIds' => explode(',', $item['metadata']['resources'] ?? null),
            ];
            $rows[$row['id']] = $row;
        }
        $rows = $this->postProcessRows($rows);

        return $this->rowsToActivities($rows);
    }
}