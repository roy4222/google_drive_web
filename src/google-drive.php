<?php
require __DIR__ . '/../vendor/autoload.php';

use Google\Client;
use Google\Service\Drive;

class GoogleDriveService {
    private $service;
    private $folderId;

    public function __construct($credentialsPath, $folderId) {
        if (!file_exists($credentialsPath)) {
            throw new Exception(sprintf(
                "Google Drive credentials file not found at: %s\nCurrent directory: %s\nFull path: %s",
                $credentialsPath,
                getcwd(),
                realpath($credentialsPath) ?: 'N/A'
            ));
        }

        if (!is_readable($credentialsPath)) {
            throw new Exception(sprintf(
                "Google Drive credentials file is not readable at: %s\nFile permissions: %s",
                $credentialsPath,
                decoct(fileperms($credentialsPath) & 0777)
            ));
        }

        try {
            $client = new Client();
            $client->setAuthConfig($credentialsPath);
            $client->addScope(Drive::DRIVE_READONLY);
            
            $this->service = new Drive($client);
            $this->folderId = $folderId;
        } catch (Exception $e) {
            throw new Exception("Google Drive initialization failed: " . $e->getMessage());
        }
    }

    public function getImageUrl($fileId) {
        try {
            $file = $this->service->files->get($fileId, ['fields' => 'webContentLink']);
            return str_replace('&export=download', '', $file->getWebContentLink());
        } catch (Exception $e) {
            return "https://drive.google.com/uc?export=view&id=" . $fileId;
        }
    }

    public function listFiles() {
        $optParams = [
            'q' => "'{$this->folderId}' in parents and mimeType contains 'image/'",
            'fields' => 'files(id, name, mimeType, webContentLink)',
        ];

        $results = $this->service->files->listFiles($optParams);
        return $results->getFiles();
    }
} 