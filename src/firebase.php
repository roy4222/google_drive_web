<?php
require __DIR__ . '/../vendor/autoload.php';

use Google\Cloud\Firestore\FirestoreClient;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

class FirebaseService {
    private $database;

    public function __construct($credentialsPath, $databaseUrl) {
        if (!file_exists($credentialsPath)) {
            throw new Exception(sprintf(
                "Firebase credentials file not found at: %s\nCurrent directory: %s\nFull path: %s",
                $credentialsPath,
                getcwd(),
                realpath($credentialsPath) ?: 'N/A'
            ));
        }

        if (!is_readable($credentialsPath)) {
            throw new Exception(sprintf(
                "Firebase credentials file is not readable at: %s\nFile permissions: %s",
                $credentialsPath,
                decoct(fileperms($credentialsPath) & 0777)
            ));
        }

        if (empty($databaseUrl)) {
            throw new Exception(sprintf(
                "Database URL is empty. Environment variables:\nFIREBASE_DATABASE_URL=%s\nAll ENV vars:\n%s",
                $_ENV['FIREBASE_DATABASE_URL'] ?? 'not set',
                print_r($_ENV, true)
            ));
        }

        try {
            $factory = (new Factory)
                ->withServiceAccount($credentialsPath)
                ->withDatabaseUri($databaseUrl);

            $this->database = $factory->createDatabase();
        } catch (Exception $e) {
            throw new Exception("Firebase initialization failed: " . $e->getMessage() . "\nDatabase URL: " . $databaseUrl);
        }
    }

    public function getImages() {
        $reference = $this->database->getReference('images');
        $snapshot = $reference->getSnapshot();
        
        if (!$snapshot->exists()) {
            return [];
        }

        return $snapshot->getValue();
    }

    public function getImageById($imageId) {
        $reference = $this->database->getReference('images/' . $imageId);
        $snapshot = $reference->getSnapshot();
        
        if (!$snapshot->exists()) {
            return null;
        }

        return $snapshot->getValue();
    }

    public function updateImage($imageId, $data) {
        try {
            $this->database
                ->getReference('images/' . $imageId)
                ->update($data);
            return true;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }

    public function deleteImage($imageId) {
        try {
            $this->database
                ->getReference('images/' . $imageId)
                ->remove();
            return true;
        } catch (Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }
} 