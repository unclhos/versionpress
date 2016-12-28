<?php

namespace VersionPress\Tests\SynchronizerTests;

use VersionPress\Storages\DirectoryStorage;
use VersionPress\Storages\MetaEntityStorage;
use VersionPress\Synchronizers\Synchronizer;
use VersionPress\Tests\SynchronizerTests\Utils\EntityUtils;
use VersionPress\Tests\Utils\DBAsserter;
use VersionPress\Utils\AbsoluteUrlReplacer;

class PostMetaSynchronizerTest extends SynchronizerTestCase
{
    /** @var MetaEntityStorage */
    private $storage;
    /** @var DirectoryStorage */
    private $postStorage;
    /** @var DirectoryStorage */
    private $userStorage;
    /** @var Synchronizer */
    private $synchronizer;
    /** @var Synchronizer */
    private $postsSynchronizer;
    /** @var Synchronizer */
    private $usersSynchronizer;
    private static $authorVpId;
    private static $postVpId;
    private static $post2VpId;
    private static $vpId;

    protected function setUp()
    {
        parent::setUp();
        $this->storage = self::$storageFactory->getStorage('postmeta');
        $this->postStorage = self::$storageFactory->getStorage('post');
        $this->userStorage = self::$storageFactory->getStorage('user');
        $this->synchronizer = new Synchronizer(
            $this->storage,
            self::$database,
            self::$schemaInfo->getEntityInfo('postmeta'),
            self::$schemaInfo,
            self::$vpidRepository,
            self::$urlReplacer,
            self::$shortcodesReplacer,
            self::$tableSchemaRepository
        );
        $this->postsSynchronizer = new Synchronizer(
            $this->postStorage,
            self::$database,
            self::$schemaInfo->getEntityInfo('post'),
            self::$schemaInfo,
            self::$vpidRepository,
            self::$urlReplacer,
            self::$shortcodesReplacer,
            self::$tableSchemaRepository
        );
        $this->usersSynchronizer = new Synchronizer(
            $this->userStorage,
            self::$database,
            self::$schemaInfo->getEntityInfo('user'),
            self::$schemaInfo,
            self::$vpidRepository,
            self::$urlReplacer,
            self::$shortcodesReplacer,
            self::$tableSchemaRepository
        );
    }

    /**
     * @test
     * @testdox Synchronizer adds new postmeta to the database
     */
    public function synchronizerAddsNewPostMetaToDatabase()
    {
        $this->createPostMeta();
        $this->usersSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        $this->postsSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);

        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer updates changed postmeta in the database
     */
    public function synchronizerUpdatesChangedPostMetaInDatabase()
    {
        $this->editPostMeta('_thumbnail_id', self::$postVpId);
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer replaces absolute URLs
     */
    public function synchronizerReplacesAbsoluteUrls()
    {
        $this->editPostMeta('some_meta', AbsoluteUrlReplacer::PLACEHOLDER);
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer removes deleted postmeta from the database
     */
    public function synchronizerRemovesDeletedPostMetaFromDatabase()
    {
        $this->deletePostMeta();
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        $this->postsSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        $this->usersSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer adds new postmeta to the database (selective synchronization)
     */
    public function synchronizerAddsNewPostMetaToDatabase_selective()
    {
        $entitiesToSynchronize = $this->createPostMeta();
        $this->usersSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);
        $this->postsSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);

        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer updates changed postmeta in the database (selective synchronization)
     */
    public function synchronizerUpdatesChangedPostMetaInDatabase_selective()
    {
        $entitiesToSynchronize = $this->editPostMeta('_thumbnail_id', self::$postVpId);
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);
        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer removes deleted postmeta from the database (selective synchronization)
     */
    public function synchronizerRemovesDeletedPostMetaFromDatabase_selective()
    {
        $entitiesToSynchronize = $this->deletePostMeta();
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);
        $this->postsSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);
        $this->usersSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);
        DBAsserter::assertFilesEqualDatabase();
    }

    private function createPostMeta()
    {
        $author = EntityUtils::prepareUser();
        self::$authorVpId = $author['vp_id'];
        $this->userStorage->save($author);

        $post = EntityUtils::preparePost(null, self::$authorVpId);
        self::$postVpId = $post['vp_id'];
        $this->postStorage->save($post);

        $post2 = EntityUtils::preparePost(null, self::$authorVpId);
        self::$post2VpId = $post2['vp_id'];
        $this->postStorage->save($post2);

        /**
         * This postmeta has a value reference to another post.
         * @see schema.yml
         * @var array
         */
        $postmeta = EntityUtils::preparePostMeta(null, self::$postVpId, '_thumbnail_id', self::$post2VpId);
        self::$vpId = $postmeta['vp_id'];
        $this->storage->save($postmeta);

        return [
            ['vp_id' => self::$authorVpId, 'parent' => self::$authorVpId],
            ['vp_id' => self::$postVpId, 'parent' => self::$postVpId],
            ['vp_id' => self::$post2VpId, 'parent' => self::$post2VpId],
            ['vp_id' => self::$vpId, 'parent' => self::$postVpId],
        ];
    }

    private function editPostMeta($key, $value)
    {
        $this->storage->save(EntityUtils::preparePostMeta(self::$vpId, self::$postVpId, $key, $value));
        return [
            ['vp_id' => self::$vpId, 'parent' => self::$postVpId],
        ];
    }

    private function deletePostMeta()
    {
        $this->storage->delete(EntityUtils::preparePostMeta(self::$vpId, self::$postVpId));
        $this->postStorage->delete(EntityUtils::preparePost(self::$postVpId));
        $this->postStorage->delete(EntityUtils::preparePost(self::$post2VpId));
        $this->userStorage->delete(EntityUtils::prepareUser(self::$authorVpId));

        return [
            ['vp_id' => self::$authorVpId, 'parent' => self::$authorVpId],
            ['vp_id' => self::$postVpId, 'parent' => self::$postVpId],
            ['vp_id' => self::$post2VpId, 'parent' => self::$post2VpId],
            ['vp_id' => self::$vpId, 'parent' => self::$postVpId],
        ];
    }
}
