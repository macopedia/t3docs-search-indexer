<?php


namespace App\Command;

use App\Dto\Manual;
use App\Event\ImportManual\ManualAdvance;
use App\Event\ImportManual\ManualFinish;
use App\Event\ImportManual\ManualStart;
use App\Service\DirectoryFinderService;
use App\Service\ImportManualHTMLService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Contracts\EventDispatcher\Event;

class SingleManualImporter extends Command
{
    private const FOLDER_DEPTH = 4;
    private const MIN_MANUAL_PATH_FOLDERS_COUNT = 3;
    private const MAX_MANUAL_PATH_FOLDERS_COUNT = 5;
    /**
     * @var string $defaultRootPath
     */
    private $defaultRootPath;

    /**
     * @var string $appRootDir
     */
    private $appRootDir;

    /**
     * @var ImportManualHTMLService $importer
     */
    private $importer;

    /**
     * @var Finder $finder
     */
    private $finder;

    private $finderPath;

    private $pathToManual;

    private $indexFolder;

    private $incrementor;

    private $parameterBag;

    private $directoryFinder;
    /** @var SymfonyStyle */
    private $io;

    /**
     * SingleManualImporter constructor.
     * @param string $defaultRootPath
     * @param string $appRootDir
     * @param ImportManualHTMLService $importer
     * @param ParameterBagInterface $parameterBag
     * @param DirectoryFinderService $directoryFinder
     */
    public function __construct(
        string $defaultRootPath,
        string $appRootDir,
        ImportManualHTMLService $importer,
        ParameterBagInterface $parameterBag,
        DirectoryFinderService $directoryFinder,
        EventDispatcherInterface $dispatcher
    )
    {
        $this->defaultRootPath = $defaultRootPath;
        $this->appRootDir = $appRootDir;
        $this->importer = $importer;
        $this->parameterBag = $parameterBag;
        $this->finder = new Finder();
        $this->incrementor = 0;
        $this->directoryFinder = $directoryFinder;
        $dispatcher->addListener(ManualStart::NAME, [$this, 'startProgress']);
        $dispatcher->addListener(ManualAdvance::NAME, [$this, 'advanceProgress']);
        $dispatcher->addListener(ManualFinish::NAME, [$this, 'finishProgress']);
        parent::__construct();
    }

    public function startProgress(ManualStart $event)
    {
        if ($this->io) {
            $this->io->progressStart($event->getFiles()->count());
        }
    }

    public function advanceProgress(Event $event)
    {
        if ($this->io) {
            $this->io->progressAdvance();
        }
    }

    public function finishProgress(Event $event)
    {
        if ($this->io) {
            $this->io->progressFinish();
        }
    }

    protected function configure()
    {
        $this->setName('docsearch:import:single-manual');
        $this->setDescription('Imports single manual');
        $this->addArgument('manualPath', InputArgument::OPTIONAL, 'Path to a single manual');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getArgument('manualPath')) {
            $this->indexManualPath($input, $output);
            return 0;
        }

        $this->selectIndexFolder($input, $output)
            ->selectSubFolder($input, $output)
            ->importManuals($input, $output);

        return 0;
    }

    private function importManuals(InputInterface $input, OutputInterface $output)
    {
        /** @var Manual $manual */
        $manual = $this->importer->findManual($this->defaultRootPath, $this->pathToManual);
        $timer = new Stopwatch();
        $timer->start('importer');
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Starting import');
        $this->io->writeln('Import manual ' . $manual->getTitle());

        $this->importer->deleteManual($manual);
        $this->importer->importManual($manual);

        $totalTime = $timer->stop('importer');
        $this->io->title('importing took ' . $this->formatMilliseconds($totalTime->getDuration()));
    }

    private function selectIndexFolder(InputInterface $input, OutputInterface $output): self
    {
        $finderPathFormat = $this->defaultRootPath . '/%s';
        $indexFolders = $this->getIndexDirectories();
        $question = new ChoiceQuestion(
            'Select folder', $indexFolders['allowed_directories']
        );
        $question->setErrorMessage('Can not index folder \'%s\'');
        $folder = $this->getQuestionHelper()->ask($input, $output, $question);

        $this->pathToManual = $folder;
        $this->finderPath = $this->appRootDir . DIRECTORY_SEPARATOR . sprintf($finderPathFormat, $folder);
        $this->indexFolder = $folder;

        return $this;
    }

    private function selectSubFolder(InputInterface $input, OutputInterface $output): self
    {
        $this->incrementor++;
        $finder = $this->finder->directories()->in($this->finderPath)->depth('== 0');
        $subcategories = [];
        $subcategoriesListOptions = [];
        $i = 0;

        /** @var SplFileInfo $folder */
        foreach ($finder->getIterator() as $folder) {
            $subcategories[$i]['dirname'] = $folder->getBasename();
            $subcategories[$i]['realPath'] = $folder->getRealPath();
            $i++;
        }

        foreach ($subcategories as $key => $value) {
            $subcategoriesListOptions[$key] = $value['dirname'];
        }

        $question = new ChoiceQuestion(
            'Select folder', $subcategoriesListOptions
        );

        $question->setErrorMessage('Can not find folder \'%s\'');
        $folder = $this->getQuestionHelper()->ask($input, $output, $question);

        $this->finderPath .= DIRECTORY_SEPARATOR . $folder;
        $this->pathToManual .= DIRECTORY_SEPARATOR . $folder;

        if ($this->incrementor < self::FOLDER_DEPTH) {
            $this->selectSubFolder($input, $output);
        }

        return $this;

    }

    /**
     * @return QuestionHelper
     */
    private function getQuestionHelper(): QuestionHelper
    {
        return $this->getHelper('question');
    }

    private function makePathRelative(string $base, string $path)
    {
        return str_replace(rtrim($base, '/') . '/', '', $path);
    }

    private function formatMilliseconds(int $milliseconds): string
    {
        $t = round($milliseconds / 1000);
        return sprintf('%02d:%02d:%02d', ($t / 3600), ($t / 60 % 60), $t % 60);
    }

    private function getIndexDirectories(): array
    {
        $docsearchConfig = $this->parameterBag->get('docsearch');

        return $docsearchConfig['indexer'];
    }

    private function indexManualPath(InputInterface $input, OutputInterface $output): int
    {
        $manualPath = $input->getArgument('manualPath');
        $manualPathArray = explode('/', $manualPath);
        $docsearchConfig = $this->getIndexDirectories();
        $allowedDirectories = $docsearchConfig['allowed_directories'];

        if (empty(array_intersect($manualPathArray, $allowedDirectories))) {
            $errorMessagePattern = '<error>Given path %s is excluded by configuration, please check services.yaml file</error>';
            $output->writeln(sprintf($errorMessagePattern, $this->pathToManual));
        }

        switch (count($manualPathArray)) {
            case count($manualPathArray) === self::MIN_MANUAL_PATH_FOLDERS_COUNT:
                $subfolders = $this->directoryFinder->findSubDirectories($manualPath, '== 1');

                foreach ($subfolders->getIterator() as $subfolder) {
                    $this->pathToManual = $manualPath . DIRECTORY_SEPARATOR . $subfolder->getRelativePathname();
                    $this->importManuals($input, $output);
                }
                return 0;
            case count($manualPathArray) > self::MIN_MANUAL_PATH_FOLDERS_COUNT && count($manualPathArray) < self::MAX_MANUAL_PATH_FOLDERS_COUNT:
                $subfolders = $this->directoryFinder->findSubDirectories($manualPath, '<= 0');

                foreach ($subfolders->getIterator() as $subfolder) {
                    $this->pathToManual = $manualPath . DIRECTORY_SEPARATOR . $subfolder->getRelativePathname();
                    $this->importManuals($input, $output);
                }
                return 0;
            case count($manualPathArray) === self::MAX_MANUAL_PATH_FOLDERS_COUNT:
                $this->importManuals($input, $output);

                return 0;
        }
    }
}