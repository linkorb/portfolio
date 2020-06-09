<?php

namespace Portfolio\Console\Command;

use RuntimeException;

use Portfolio\Model;
use Portfolio\ActivityAdapter;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Collection\TypedArray;

class PullCommand extends Command
{
    public function configure()
    {
        $this->setName('pull')
            ->setDescription('Pull')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $portfolio = Model\Portfolio::fromEnv();
        // print_r($portfolio);
        foreach ($portfolio->getProjects() as $project) {
            // print_r($project);
            $config = $project->getActivityAdapter();
            $activities = [];
            if (isset($config['type'])) {
                switch ($config['type']) {
                    case 'google-spreadsheet':
                        $source = new ActivityAdapter\GoogleSpreadsheetActivityAdapter();
                        $activities = $source->getActivities($project, $config);
                        break;
                    case 'criterion':
                        $source = new ActivityAdapter\CriterionActivityAdapter();
                        $activities = $source->getActivities($project, $config);
                        break;
                    default:
                        throw new RuntimeException("Unsupported activity source type: " . $config['type']);
                }
            }
            // echo $project->getId()  . ' activities: ' .count($activities) . PHP_EOL;
            foreach ($activities as $activity) {
                $project->getActivities()->add($activity);
            }
        }
        // print_r($portfolio);


        $portfolioPath = $portfolio->getPath();
        foreach ($portfolio->getProjects() as $project) {
            $projectPath = $portfolioPath . '/projects/' . $project->getId();
            if (!file_exists($projectPath)) {
                throw new RuntimeException("Project path not found: " . $projectPath);
            }
            $activityPath = $projectPath . '/activities';
            if (!file_exists($activityPath)) {
                throw new RuntimeException("Project activity path not found: " . $activityPath);
            }

            foreach ($project->getActivities() as $activity) {
                $data = $activity->toArray();
                // print_r($data);
                $yaml = Yaml::dump(
                    $data,
                    10 /* levels */,
                    2 /* indention */,
                    Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
                );
                // echo $yaml;
                file_put_contents($activityPath . '/' . $activity->getId() . '.yaml', $yaml);
            }
        }
        
    }
}
