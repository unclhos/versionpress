<?php

namespace VersionPress\Tests\SynchronizerTests;

use VersionPress\Storages\DirectoryStorage;
use VersionPress\Synchronizers\Synchronizer;
use VersionPress\Tests\SynchronizerTests\Utils\EntityUtils;
use VersionPress\Tests\Utils\DBAsserter;
use VersionPress\Utils\AbsoluteUrlReplacer;

class TermsSynchronizerTest extends SynchronizerTestCase
{
    /** @var DirectoryStorage */
    private $storage;
    /** @var Synchronizer */
    private $synchronizer;
    private static $vpId;

    protected function setUp()
    {
        parent::setUp();
        $this->storage = self::$storageFactory->getStorage('term');
        $this->synchronizer = new Synchronizer(
            $this->storage,
            self::$database,
            self::$schemaInfo->getEntityInfo('term'),
            self::$schemaInfo,
            self::$vpidRepository,
            self::$urlReplacer,
            self::$shortcodesReplacer,
            self::$tableSchemaRepository
        );
    }

    /**
     * @test
     * @testdox Synchronizer adds new term to the database
     */
    public function synchronizerAddsNewTermToDatabase()
    {
        $this->createTerm();
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer updates changed term in the database
     */
    public function synchronizerUpdatesChangedTermInDatabase()
    {
        $this->editTerm();
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer replaces absolute URLs
     */
    public function synchronizerReplacesAbsoluteUrls()
    {
        $this->editTerm(AbsoluteUrlReplacer::PLACEHOLDER);
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer removes deleted term from the database
     */
    public function synchronizerRemovesDeletedTermFromDatabase()
    {
        $this->deleteTerm();
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer adds new term to the database (selective synchronization)
     */
    public function synchronizerAddsNewTermToDatabase_selective()
    {
        $entitiesToSynchronize = $this->createTerm();
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);
        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer updates changed term in the database (selective synchronization)
     */
    public function synchronizerUpdatesChangedTermInDatabase_selective()
    {
        $entitiesToSynchronize = $this->editTerm();
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);
        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer removes deleted term from the database (selective synchronization)
     */
    public function synchronizerRemovesDeletedTermFromDatabase_selective()
    {
        $entitiesToSynchronize = $this->deleteTerm();
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);
        DBAsserter::assertFilesEqualDatabase();
    }

    private function createTerm()
    {
        $term = EntityUtils::prepareTerm(null, 'Some term', 'some-term');
        self::$vpId = $term['vp_id'];
        $this->storage->save($term);
        return [['vp_id' => self::$vpId, 'parent' => self::$vpId]];
    }

    private function editTerm($name = 'Another name')
    {
        $this->storage->save(EntityUtils::prepareTerm(self::$vpId, $name));
        return [['vp_id' => self::$vpId, 'parent' => self::$vpId]];
    }

    private function deleteTerm()
    {
        $this->storage->delete(EntityUtils::prepareTerm(self::$vpId));
        return [['vp_id' => self::$vpId, 'parent' => self::$vpId]];
    }
}
