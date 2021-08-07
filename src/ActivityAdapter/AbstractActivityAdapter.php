<?php

namespace Portfolio\ActivityAdapter;

use Portfolio\Model;

abstract class AbstractActivityAdapter
{
    protected function rowsToActivities(array $rows): array
    {
        $activities = [];
        foreach ($rows as $row) {
            $activity = Model\Activity::fromArray($row);
            $activities[$activity->getId()] = $activity;
        }
        return $activities;
    }

    protected function parseTsv(string $tsv): array
    {
        $lines=explode("\n", $tsv);
        $header = $lines[0];
        $columns = explode("\t", $header);
        // print_r($columns);
        $rows=[];
        for ($i=1;$i<count($lines); $i++) {
            $line = $lines[$i];
            $row = [];
            $cells = explode("\t", $line);
            if (count($cells)>count($columns)) {
                throw new RuntimeException("More cells than columns in line " . $j);
            }
            foreach ($cells as $j=>$value) {
                $value = trim($value);
                $key = trim($columns[$j]);
                $row[$key] = $value;

                // echo "$key=$value\n";
            }
            $rows[$row['id']] = $row;
        }
        return $rows;
    }

    protected function postProcessRows(array $rows): array
    {
        $levels = [];
        foreach ($rows as &$row) {
            $effort = strtolower($row['effort'] ?? null) ?? null;
            $row['children'] = [];

            if (substr($effort, -1, 1)=='h') {
                $effort = (int)substr($effort, 0, -1);
                $row['effort'] = $effort;
            }

            if (substr($effort, -1, 1)=='d') {
                $effort = (int)substr($effort, 0, -1);
                $effort = $effort * 8;
                $row['effort'] = $effort;
            }

            if (substr($effort, -1, 1)=='m') {
                $effort = (int)substr($effort, 0, -1);
                $effort = round($effort / 60, 1);
                $row['effort'] = $effort;
            }

            $title = trim($row['title']);
            $title = str_replace('*  ', '*', $title);
            $title = str_replace('* ', '*', $title);
            $len = strlen($title);
            $title = ltrim($title, '*');
            $level = $len - strlen($title);
            $row['title'] = $title;
            $row['level'] = $level;
            $levels[$level] = $row['id'];

            if (isset($row['predecessors'])) {
                $row['predecessorIds'] = explode(',', $row['predecessors']);
            }
            if (isset($row['resources'])) {
                $resourceIds = $row['resources'] ?? null;
                if (is_string($resourceIds)) {
                    // support comma-seperated string as an array
                    $resourceIds = explode(',', $resourceIds);
                }
                $row['resourceIds'] = $resourceIds;
            }

            if (!isset($row['parentId'])) {
                // determine parentId from hierarchy
                $parentId = null;
                if ($level>0) {
                    $parentId = $levels[$level-1];
                }
                $row['parentId'] = $parentId;
            }

            // $res[$row['id']] = $row;
        }

        foreach ($rows as &$row) {
            $id = $row['id'];

            $parentId = $row['parentId'] ?? null;
            if ($parentId) {
                if (!isset($rows[$parentId])) {
                    // referencing parent outside of this project/activity list?
                } else {
                    $parent = &$rows[$parentId];
                    if ($parent) {
                        array_push($parent['children'], $id);
                    }
                }
            }

        }

        foreach ($rows as &$row) {
            if (count($row['children'])>0) {
                $row['type'] = 'section';
            } else {
                $row['type'] = 'task';
            }
        }
        return $rows;
    }
}
