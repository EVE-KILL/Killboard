<?php

namespace EK\Controllers\Api;

use EK\Api\Abstracts\Controller;
use EK\Api\Attributes\RouteAttribute;
use EK\Models\Comments as ModelsComments;
use EK\Models\Users;
use Psr\Http\Message\ResponseInterface;
use Sirius\Validation\Validator;

class Comments extends Controller
{
    public function __construct(
        protected ModelsComments $comments,
        protected Users $users
    ) {
    }

    #[RouteAttribute("/comments[/]", ["GET"], "Get all comments")]
    public function getComments(): ResponseInterface
    {
        $comments = $this->comments->find([], [
            'projection' => ['_id' => 0],
        ]);

        $comments = $this->cleanupTimestamps($comments->toArray());

        return $this->json($comments);
    }

    #[RouteAttribute("/comments/url/{url}[/]", ["GET"], "Get comments by URL")]
    public function getCommentsByUrl(string $url): ResponseInterface
    {
        $comments = $this->comments->find(['url' => $url], [
            'projection' => ['_id' => 0],
        ]);

        $comments = $this->cleanupTimestamps($comments->toArray());

        return $this->json($comments);
    }

    #[RouteAttribute("/comments[/]", ["POST"], "Get comments by URL")]
    public function getCommentsForURL(): ResponseInterface
    {
        $url = $this->getBody();
        if (empty($url)) {
            return $this->json(["error" => "No data provided"], 300);
        }

        // Take the first URL from the array
        if ($url === null) {
            return $this->json(["error" => "No URL provided"], 300);
        }

        $comments = $this->comments->find(['url' => $url], [
            'projection' => ['_id' => 0],
        ]);

        $comments = $this->cleanupTimestamps($comments->toArray());

        return $this->json($comments);
    }

    #[RouteAttribute("/comments/post[/]", ["POST"], "Post a comment")]
    public function postComment(): ResponseInterface
    {
        $postData = json_validate($this->getBody())
            ? json_decode($this->getBody(), true)
            : [];
        if (empty($postData)) {
            return $this->json(["error" => "No data provided"], 300);
        }

        $validator = new Validator();
        // Validate the input
        $validator->add([
            'body' => 'required | maxlength(1024)',
            'identifier' => 'required',
            'url' => 'required',
        ]);

        if (!$validator->validate($postData)) {
            return $this->json(["error" => "Invalid data!"], status: 400);
        }

        // Validate the user
        if ($this->users->validateIdentifier($postData['identifier'])) {
            $user = $this->users->getUserByIdentifier($postData['identifier']);
        } else {
            return $this->json(["error" => "Invalid user!"], status: 400);
        }

        $this->comments->setData([
            'body' => $postData['body'],
            'character' => [
                'character_id' => $user['character_id'],
                'character_name' => $user['character_name'],
            ],
            'url' => $postData['url'],
        ]);
        $this->comments->save();

        return $this->json(['success' => true]);
    }

    #[RouteAttribute("/comments/{commentId:[0-9]+}[/]", ["GET"], "Get a comment by ID")]
    public function getComment(int $commentId): ResponseInterface
    {
        $comment = $this->comments->findOne(
            ['comment_id' => $commentId],
            [
                'projection' => ['_id' => 0],
            ]
        );

        return $this->json($comment);
    }
}
