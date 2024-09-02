<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Models\Comments as CommentsModel;
use EK\Models\Users;
use Psr\Http\Message\ResponseInterface;
use Sirius\Validation\Validator;

class Comments extends Controller
{
    public function __construct(
        protected CommentsModel $comments,
        protected Users $users
    ) {
    }

    #[RouteAttribute("/comments/{identifier}[/]", ["GET"], "Get all comments for a particular identifier")]
    public function getComments(string $identifier): ResponseInterface
    {
        $comments = $this->comments->find(["identifier" => $identifier], ["projection" => ["_id" => 0]]);
        return $this->json($comments->toArray(), 300);
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
        $comment = $postData['comment'];

        $commentObject = [
            'identifier' => $identifier,
            'comment' => $comment,
            'character' => [
                'character_id' => $user['character_id'],
                'character_name' => $user['character_name']
            ]
        ];

        $this->comments->setData($commentObject);
        $result = $this->comments->save();

        return $this->json([$commentObject, $result], 300);
    }
}
