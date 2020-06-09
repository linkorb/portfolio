<?php

namespace Portfolio\Console\Command;

use RuntimeException;

use Portfolio\Model;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Collection\TypedArray;

class ConfigCommand extends Command
{
    public function configure()
    {
        $this->setName('config')
            ->setDescription('Config')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $portfolio = Model\Portfolio::fromEnv();
        $portfolio->loadActivities();
        print_r($portfolio);
    }
}
