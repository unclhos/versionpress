<?php

namespace VersionPress\Tests\SynchronizerTests;

use VersionPress\Storages\DirectoryStorage;
use VersionPress\Synchronizers\Synchronizer;
use VersionPress\Tests\SynchronizerTests\Utils\EntityUtils;
use VersionPress\Tests\Utils\DBAsserter;
use VersionPress\Utils\AbsoluteUrlReplacer;

class CommentsSynchronizerTest extends SynchronizerTestCase
{
    /** @var DirectoryStorage */
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
    private static $vpId;

    protected function setUp()
    {
        parent::setUp();
        $this->storage = self::$storageFactory->getStorage('comment');
        $this->postStorage = self::$storageFactory->getStorage('post');
        $this->userStorage = self::$storageFactory->getStorage('user');
        $this->synchronizer = new Synchronizer(
            $this->storage,
            self::$database,
            self::$schemaInfo->getEntityInfo('comment'),
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
     * @testdox Synchronizer adds new comment to the database
     */
    public function synchronizerAddsNewCommentToDatabase()
    {
        $this->createComment();
        $this->usersSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        $this->postsSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);

        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer updates changed comment in the database
     */
    public function synchronizerUpdatesChangedCommentInDatabase()
    {
        $this->editComment();
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer replaces absolute URLs
     */
    public function synchronizerReplacesAbsoluteUrls()
    {
        $this->editComment('comment_content', AbsoluteUrlReplacer::PLACEHOLDER);
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer removes deleted comment from the database
     */
    public function synchronizerRemovesDeletedCommentFromDatabase()
    {
        $this->deleteComment();
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        $this->postsSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        $this->usersSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING);
        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer adds new comment to the database (selective synchronization)
     */
    public function synchronizerAddsNewCommentToDatabase_selective()
    {
        $entitiesToSynchronize = $this->createComment();
        $this->usersSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);
        $this->postsSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);

        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer updates changed comment in the database (selective synchronization)
     */
    public function synchronizerUpdatesChangedCommentInDatabase_selective()
    {
        $entitiesToSynchronize = $this->editComment();
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);
        DBAsserter::assertFilesEqualDatabase();
    }

    /**
     * @test
     * @testdox Synchronizer removes deleted comment from the database (selective synchronization)
     */
    public function synchronizerRemovesDeletedCommentFromDatabase_selective()
    {
        $entitiesToSynchronize = $this->deleteComment();
        $this->synchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);
        $this->postsSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);
        $this->usersSynchronizer->synchronize(Synchronizer::SYNCHRONIZE_EVERYTHING, $entitiesToSynchronize);
        DBAsserter::assertFilesEqualDatabase();
    }

    private function createComment()
    {
        $author = EntityUtils::prepareUser();
        self::$authorVpId = $author['vp_id'];
        $this->userStorage->save($author);

        $post = EntityUtils::preparePost(null, self::$authorVpId);
        self::$postVpId = $post['vp_id'];
        $this->postStorage->save($post);

        $comment = EntityUtils::prepareComment(null, self::$postVpId, self::$authorVpId);
        self::$vpId = $comment['vp_id'];
        /** @noinspection PhpUsageOfSilenceOperatorInspection The SQL query does not return a post because the storage
         * is not yet synchronized. It's OK in this case. */
        @$this->storage->save($comment);

        return [
            ['vp_id' => self::$authorVpId, 'parent' => self::$authorVpId],
            ['vp_id' => self::$postVpId, 'parent' => self::$postVpId],
            ['vp_id' => self::$vpId, 'parent' => self::$vpId],
        ];
    }

    private function editComment($key = 'comment_content', $value = 'another content')
    {
        $this->storage->save(EntityUtils::prepareComment(self::$vpId, null, null, [$key => $value]));
        return [
            ['vp_id' => self::$vpId, 'parent' => self::$vpId],
        ];
    }

    private function deleteComment()
    {
        $this->storage->delete(EntityUtils::prepareComment(self::$vpId));
        $this->postStorage->delete(EntityUtils::preparePost(self::$postVpId));
        $this->userStorage->delete(EntityUtils::prepareUser(self::$authorVpId));

        return [
            ['vp_id' => self::$authorVpId, 'parent' => self::$authorVpId],
            ['vp_id' => self::$postVpId, 'parent' => self::$postVpId],
            ['vp_id' => self::$vpId, 'parent' => self::$vpId],
        ];
    }
}
