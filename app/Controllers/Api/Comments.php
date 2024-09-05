<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Helpers\Comments as CommentsHelper;
use EK\Models\Characters;
use EK\Models\Comments as CommentsModel;
use EK\Models\Users;
use MongoDB\BSON\UTCDateTime;
use Psr\Http\Message\ResponseInterface;
use Sirius\Validation\Validator;

class Comments extends Controller
{
    public function __construct(
        protected CommentsModel $comments,
        protected CommentsHelper $commentsHelper,
        protected Users $users,
        protected Characters $characters
    ) {
    }

    #[RouteAttribute("/comments/{identifier}[/]", ["GET"], "Get all comments for a particular identifier")]
    public function getComments(string $identifier): ResponseInterface
    {
        $comments = $this->comments->find(
            [
                "identifier" => $identifier
            ],
            [
                "sort" => ['created_at' => -1],
                "projection" => ["_id" => 0, "last_modified" => 0]
            ],
            cacheTime: 0
        )->toArray();
        foreach($comments as $key => $comment) {
            $comments[$key] = $this->cleanupTimestamps($comment);
            $comments[$key]['character'] = $this->characters->findOne(['character_id' => $comment['character']['character_id']], [
                'projection' => [
                    '_id' => 0,
                    'character_id' => 1,
                    'name' => 1,
                    'corporation_id' => 1,
                    'corporation_name' => 1,
                    'alliance_id' => 1,
                    'alliance_name' => 1,
                ]
            ]);

            // Rename name to character_name
            $comments[$key]['character']['character_name'] = $comments[$key]['character']['name'];
            unset($comments[$key]['character']['name']);
        }

        return $this->json($comments, 0);
    }

    #[RouteAttribute("/comments/{identifier}[/]", ["POST"], "Add a comment to a particular identifier")]
    public function addComment(string $identifier): ResponseInterface
    {
        $postData = json_validate($this->getBody()) ? json_decode($this->getBody(), true) : [];
        if (empty($postData['comment'])) {
            return $this->json(['error' => 'Comment is required'], 300);
        }

        $validator = new Validator();
        $validator->add('comment', 'required');
        $validator->add('identifier', 'required');

        if (!$validator->validate($postData)) {
            return $this->json(['error' => $validator->getMessages()], 300);
        }

        $user = $this->users->getUserByIdentifier($postData['identifier']);
        if (empty($user)) {
            return $this->json(['error' => 'User not found'], 300);
        }

        $comment = $postData['comment'];
        if (strlen($comment) > 500) {
            return $this->json(['error' => 'Comment is too long'], 300);
        }

        $moderation = $this->commentsHelper->aiModeration($comment);
        if ($this->commentsHelper->isFlagged($moderation)) {
            return $this->json(['error' => 'Comment is not allowed'], 300);
        }

        $commentObject = [
            'identifier' => $identifier,
            'comment' => $comment,
            'created_at' => new UTCDateTime(time() * 1000),
            'character' => [
                'character_id' => $user['character_id'],
                'character_name' => $user['character_name']
            ],
            'moderation' => $moderation
        ];

        $this->comments->setData($commentObject);
        $result = $this->comments->save();

        $this->commentsHelper->emitToDiscord($commentObject);

        return $this->json($commentObject, 0);
    }

    #[RouteAttribute("/comments/{identifier}/count[/]", ["GET"], "Get the number of comments for a particular identifier")]
    public function getCommentCount(string $identifier): ResponseInterface
    {
        $count = $this->comments->count(['identifier' => $identifier]);
        return $this->json(['count' => $count], 0);
    }
}
