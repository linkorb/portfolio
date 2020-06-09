<?php

namespace Portfolio\ActivityAdapter;

use Portfolio\Model;

class GoogleSpreadsheetActivityAdapter extends AbstractActivityAdapter
{
    public function getActivities(Model\Project $project, array $config): array
    {
        $documentId = $config['documentId'];
        $gid = $config['gid'] ?? 0;
        
        $url = 'https://docs.google.com/spreadsheets/d/';
        $url .= $documentId . '/export?format=tsv&gid=' . $gid;
        echo $url . PHP_EOL;
        $tsv = file_get_contents($url);
        echo $tsv . PHP_EOL . PHP_EOL;
        $rows = $this->parseTsv($tsv);
        $rows = $this->postProcessRows($rows);
        // print_r($rows);

        return $this->rowsToActivities($rows);
    }
}