<?php

namespace VersionPress\Tests\End2End\Revert;

use Nette\Utils\Random;
use VersionPress\Git\GitRepository;
use VersionPress\Tests\End2End\Utils\WpCliWorker;
use VersionPress\Tests\Utils\TestConfig;

class RevertTestWpCliWorker extends WpCliWorker implements IRevertTestWorker
{

    /** @var GitRepository */
    private $repository;

    public function __construct(TestConfig $testConfig)
    {
        parent::__construct($testConfig);
        $this->repository = new GitRepository($this->testConfig->testSite->path);
    }

    public function prepare_undoLastCommit()
    {
        $this->createTestPost();
        return [['D', '%vpdb%/posts/*']];
    }

    public function undoLastCommit()
    {
        $lastCommit = $this->repository->getLastCommitHash();
        try {
            $this->wpAutomation->runWpCliCommand('vp', 'undo', [$lastCommit]);
        } catch (\Exception $e) {
        } // Intentionally empty catch. It may throw an expcetion if the status code is not 0.
    }

    public function prepare_undoSecondCommit()
    {
        $this->wpAutomation->editOption('blogname', 'Random blogname for undo test ' . Random::generate());
        $this->createTestPost();
        return [['M', '%vpdb%/options/*']];
    }

    public function undoSecondCommit()
    {
        $log = $this->repository->log("HEAD~2..HEAD~1");
        $secondCommit = $log[0]->getHash();
        $this->wpAutomation->runWpCliCommand('vp', 'undo', [$secondCommit]);
    }

    public function prepare_undoRevertedCommit()
    {
        $this->createTestPost();
        return [['A', '%vpdb%/posts/*']];
    }

    public function prepare_tryRestoreEntityWithMissingReference()
    {
        $postId = $this->createTestPost();
        $commentId = $this->createCommentForPost($postId);
        $this->wpAutomation->deleteComment($commentId);
        $this->wpAutomation->deletePost($postId);
    }

    public function tryRestoreEntityWithMissingReference()
    {
        try {
            $this->undoSecondCommit();
        } catch (\Exception $e) {
        } // Intentionally empty catch. Violated referetial integrity throws an exception.
    }

    public function prepare_rollbackMoreChanges()
    {
        $postId = $this->createTestPost();
        $this->createCommentForPost($postId);
        $this->wpAutomation->editOption('blogname', 'Random blogname for rollback test ' . Random::generate());
        return [
            ['D', '%vpdb%/posts/*'],
            ['D', '%vpdb%/comments/*'],
            ['M', '%vpdb%/options/*'],
        ];
    }

    public function rollbackMoreChanges()
    {
        $log = $this->repository->log("HEAD~4..HEAD~3");
        $commitForRollback = $log[0]->getHash();
        $this->wpAutomation->runWpCliCommand('vp', 'rollback', [$commitForRollback]);
    }

    public function prepare_clickOnCancel()
    {
        throw new \PHPUnit_Framework_SkippedTestError("There is no cancel button in the WP-CLI");
    }

    public function clickOnCancel()
    {
    }

    public function prepare_undoWithNotCleanWorkingDirectory()
    {
        $this->createTestPost();
    }

    public function prepare_undoToTheSameState()
    {
        $this->createTestPost();
        $this->undoLastCommit();
    }

    public function prepare_rollbackToTheSameState()
    {
        $postId = $this->createTestPost();
        $this->wpAutomation->deletePost($postId);
    }

    public function rollbackToTheSameState()
    {
        $log = $this->repository->log("HEAD~3..HEAD~2");
        $commitForRollback = $log[0]->getHash();
        $this->wpAutomation->runWpCliCommand('vp', 'rollback', [$commitForRollback]);
    }

    public function prepare_undoMultipleCommits()
    {
        $this->wpAutomation->editOption('blogname', 'Random blogname for undo test ' . Random::generate());
        $this->createTestPost();
        return [
            ['M', '%vpdb%/options/*'],
            ['D', '%vpdb%/posts/*']
        ];
    }

    public function undoMultipleCommits()
    {
        $log = $this->repository->log("HEAD~2..HEAD");
        $firstCommit = $log[0]->getHash();
        $secondCommit = $log[1]->getHash();

        $commits = [$firstCommit, $secondCommit];
        $this->wpAutomation->runWpCliCommand('vp', 'undo', [implode(',', $commits)]);
    }

    public function prepare_undoMultipleDependentCommits()
    {
        $postId = $this->createTestPost();
        $this->createCommentForPost($postId);
        return [
            ['D', '%vpdb%/posts/*'],
            ['D', '%vpdb%/comments/*']
        ];
    }

    public function undoMultipleDependentCommits()
    {
        $log = $this->repository->log("HEAD~2..HEAD");
        $firstCommit = $log[0]->getHash();
        $secondCommit = $log[1]->getHash();

        $commits = [$firstCommit, $secondCommit];
        $this->wpAutomation->runWpCliCommand('vp', 'undo', [implode(',', $commits)]);
    }

    public function prepare_undoMultipleCommitsThatCannotBeReverted()
    {
        $postId = $this->createTestPost();
        $commentId = $this->createCommentForPost($postId);
        $this->wpAutomation->deleteComment($commentId);
        $this->wpAutomation->deletePost($postId);
        $this->createTestPost();
    }

    public function undoMultipleCommitsThatCannotBeReverted()
    {
        try {
            $log = $this->repository->log("HEAD~3..HEAD");
            $firstCommit = $log[0]->getHash();
            $secondCommit = $log[2]->getHash();

            $commits = [$firstCommit, $secondCommit];
            $this->wpAutomation->runWpCliCommand('vp', 'undo', [implode(',', $commits)]);
        } catch (\Exception $e) {
        } // Intentionally empty catch. Violated referetial integrity throws an exception.
    }

    public function prepare_undoNonDbChange()
    {
        $newFile = 'vp-file.txt';
        file_put_contents($this->testConfig->testSite->path . '/' . $newFile, '');
        $this->repository->stageAll($newFile);
        $this->repository->commit('Manual commit', 'John Tester', 'john.tester@example.com');
        return [['D', $newFile]];
    }

    public function undoNonDbChange()
    {
        $this->undoLastCommit();
    }

    //---------------------
    // Helper methods
    //---------------------

    private function createTestPost()
    {
        $post = [
            "post_type" => "post",
            "post_status" => "publish",
            "post_title" => "Test post for revert",
            "post_date" => "2011-11-11 11:11:11",
            "post_content" => "Test post",
            "post_author" => 1
        ];

        return $this->wpAutomation->createPost($post);
    }

    private function createCommentForPost($postId)
    {
        $comment = [
            "comment_author" => "Mr VersionPress",
            "comment_author_email" => "versionpress@example.com",
            "comment_author_url" => "https://wordpress.org/",
            "comment_date" => "2012-12-12 12:12:12",
            "comment_content" => "Have you heard about VersionPress? " .
                "It's new awesome version control plugin for WordPress.",
            "comment_approved" => 1,
            "comment_post_ID" => $postId,
        ];

        return $this->wpAutomation->createComment($comment);
    }
}
