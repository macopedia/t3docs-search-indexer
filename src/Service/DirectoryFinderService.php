<?php

namespace App\Service;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\KernelInterface;

class DirectoryFinderService
{
    /** @var Finder */
    private $finder;
    /** @var KernelInterface */
    private $kernel;
    /** @var ConsoleOutputMessageService */
    private $consoleOutputMessage;

    public function __construct(KernelInterface $kernel, ConsoleOutputMessageService $consoleOutputMessage)
    {
        $this->finder = new Finder();
        $this->kernel = $kernel;
        $this->consoleOutputMessage = $consoleOutputMessage;
    }

    /**
     * @param string $rootPath
     */
    public function findAllowedDirectoriesForDocSearch(string $rootPath): ConsoleMessagePayload
    {
        return $this->getAllowedDirectories($rootPath);
    }

    /**
     * @return Finder
     */
    public function getFinder(): Finder
    {
        return $this->finder;
    }

    /**
     * @return KernelInterface
     */
    public function getKernel(): KernelInterface
    {
        return $this->kernel;
    }

    /**
     * @param $rootPath
     * @return ConsoleMessagePayload
     */
    private function getAllowedDirectories(string $rootPath): ConsoleMessagePayload
    {
        $projectDir = $this->kernel->getProjectDir();
        $docSearchParameters = $this->kernel->getContainer()->getParameter('docsearch');
        $indexerParameters = $docSearchParameters['indexer'];
        $allowedDirectories = $indexerParameters['allowed_directories'];
        $foundDirectories = [];
        $manualsPathCollection = [];
        $response['message'] = '';
        $response['manualsPath'] = [];
        $targetPath = $projectDir . DIRECTORY_SEPARATOR . $rootPath;

        if (!is_dir($targetPath)) {
            return $this->consoleOutputMessage
                ->setPath($targetPath)
                ->createMessageResponse(
                    ConsoleOutputMessageService::INVALID_ROOT_PATH_TYPE,
                );
        }

        $folders = $this->finder
            ->directories()
            ->name($allowedDirectories)
            ->in($targetPath)
        ;

        /** @var SplFileInfo $folder */
        foreach ($folders->getIterator() as $folder) {
            $foundDirectories[] = $folder->getBasename();
        }

        $missingDirectories = array_diff($allowedDirectories, $foundDirectories);

        if (count($missingDirectories) === count($allowedDirectories)) {
            return $this->consoleOutputMessage
                ->setPath($rootPath)
                ->setMissingDirectories($missingDirectories)
                ->createMessageResponse(ConsoleOutputMessageService::DIRECTORIES_NOT_FOUND_TYPE);
        }

        if (count($missingDirectories) < count($allowedDirectories)) {
            return $this->consoleOutputMessage
                ->setPath($rootPath)
                ->setMissingDirectories($missingDirectories)
                ->setFoundDirectories($foundDirectories)
                ->createMessageResponse(ConsoleOutputMessageService::SOME_DIRECTORIES_MISSING_TYPE);
        }

        return $this->consoleOutputMessage
            ->setPath($rootPath)
            ->setFoundDirectories($foundDirectories)
            ->createMessageResponse(ConsoleOutputMessageService::ALL_DIRECTORIES_FOUND_TYPE);
    }

    /**
     * @param string $message
     * @param array $manualLinks
     * @return array
     */
    private function createResponse(string $message, array $manualLinks): array
    {
        return [
            'message' => $message,
            'manualsPath' => $manualLinks
        ];
    }
}
