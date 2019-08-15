<?php

/*
 * This file is part of the Pho package.
 *
 * (c) Emre Sokullu <emre@phonetworks.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

 namespace GraphJS\Controllers;

use CapMousse\ReactRestify\Http\Request;
use CapMousse\ReactRestify\Http\Response;
use Pho\Kernel\Kernel;
use PhoNetworksAutogenerated\User;
use PhoNetworksAutogenerated\UserOut\Star;
use PhoNetworksAutogenerated\UserOut\Comment;
use Pho\Lib\Graph\ID;


/**
 * Administrative calls go here.
 * 
 * @author Emre Sokullu <emre@phonetworks.org>
 */
class AdministrationController extends AbstractController
{

    /**
     * SuperAdmin Hash
     * 
     * Generated randomly by a uuidgen
     *
     * @var string
     */
    protected $superadmin_hash = ""; // not a good idea, since it's public

    protected function requireAdministrativeRights(Request $request, Response $response, Kernel $kernel): bool
    {
        //$founder = $kernel->founder();
        //$hash = md5(strtolower(sprintf("%s:%s", $founder->getEmail(), $founder->getPassword())));
        $hash = md5(getenv("FOUNDER_PASSWORD"));
        error_log("founder password is: ".getenv("FOUNDER_PASSWORD"));
        error_log("hash is: ".$hash);
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            "hash" => "required"
        ]);
        //$v->rule('length', [['hash', 32]]);
        //error_log($founder->getEmail().":".$founder->getPassword().":".$hash);
        error_log("data hash is: ".$data["hash"]);
        if($validation->fails()||($data["hash"]!=$hash&&$data["hash"]!=$this->superadmin_hash)) {
            return false;
        }
        return true;
    }

    protected function _getPendingComments(Kernel $kernel): array
    {
        $pending_comments = [];
        $res = $kernel->index()->query("MATCH (a:user)-[e:comment {Pending: true}]-(n:page) RETURN a.udid AS author_id, a.Email AS author_email, n.udid AS page_id, e.udid AS comment_id, n.Url AS page_url, n.Title AS page_title, e.Content AS comment");
        $array = $res->results();
        foreach($array as $a) {
            $pending_comments[] = [
                "comment_id" => $a["comment_id"],
                "author_id" => $a["author_id"], 
                "author_email" => $a["author_email"], 
                "page_id" => $a["page_id"], 
                "page_url" => $a["page_url"],
                "page_title" => $a["page_title"],
                "comment" => $a["comment"]
            ];
        }
        return $pending_comments;
    }

    public function fetchAllPendingComments(Request $request, Response $response, Kernel $kernel)
    {
        if(!$this->requireAdministrativeRights(...\func_get_args()))
            return $this->fail($response, "Invalid hash");
        $is_moderated = ($kernel->graph()->getCommentsModerated() === true);
        /*if(!$is_moderated)
            return $this->fail($response);
        */
        $pending_comments = $this->_getPendingComments($kernel);
        $this->succeed($response, ["pending_comments"=>$pending_comments]);
    }

    /**
     * @todo Check for admin capabilities
     */
    public function approvePendingComment(Request $request, Response $response, Kernel $kernel)
    {
        if(!$this->requireAdministrativeRights(...\func_get_args()))
            return $this->fail($response, "Invalid hash");
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            "comment_id" => "required"
        ]);
        if($validation->fails()) {
            $this->fail($response, "comment_id required");
            return;
        }
        try {
            $comment = $kernel->gs()->edge($data["comment_id"]);
        }
        catch(\Exception $e) {
            $this->fail($response, "Invalid Comment ID.");
            return;
        }
        if(!$comment instanceof Comment)
            return $this->fail($response, "Invalid Comment.");
        $comment->setPending(false);
        $this->succeed($response);
    }

    public function setBlogEditor(Request $request, Response $response, Kernel $kernel)
    {
        if(!$this->requireAdministrativeRights(...\func_get_args()))
            return $this->fail($response, "Invalid hash");

            $data = $request->getQueryParams();
            $validation = $this->validator->validate($data, [
                "user_id" => "required",
            ]);
            if($validation->fails()) {
                return $this->fail($response, "A user id  is required");
            }
            $is_editor = isset($data["is_editor"]) && (bool) $data["is_editor"];
            try {
                $user = $kernel->gs()->node($data["user_id"]);
            }
            catch(\Exception $e) {
                return $this->fail($response, "Invalid user id");
            }
            $user->attributes()->is_editor = (bool) ($is_editor);
            $this->succeed($response);
    }

    public function setCustomFields(Request $request, Response $response, Kernel $kernel)
    {
        if(!$this->requireAdministrativeRights(...\func_get_args()))
            return $this->fail($response, "Invalid hash");

        // only works with Graph.js
        if(!$kernel->graph() instanceof \PhoNetworksAutogenerated\Site) {
            return $this->fail($response, "Invalid function");
        }

        $data = $request->getQueryParams();
        $custom_field1 = (string) $data["custom_field1"];
        $custom_field2 = (string) $data["custom_field2"];
        $custom_field3 = (string) $data["custom_field3"];

        $kernel->graph()->attributes()->custom_field1 = $custom_field1;
        $kernel->graph()->attributes()->custom_field2 = $custom_field2;
        $kernel->graph()->attributes()->custom_field3 = $custom_field3;
        
        $kernel->graph()->persist();
        $this->succeed($response);
    }

    public function getCustomFields(Request $request, Response $response, Kernel $kernel)
    {
        if(!$this->requireAdministrativeRights(...\func_get_args()))
            return $this->fail($response, "Invalid hash");

        // only works with Graph.js
        if(!$kernel->graph() instanceof \PhoNetworksAutogenerated\Site) {
            return $this->fail($response, "Invalid function");
        }

        $custom_field1 = (string) $kernel->graph()->attributes()->custom_field1;
        $custom_field2 = (string) $kernel->graph()->attributes()->custom_field2;
        $custom_field3 = (string) $kernel->graph()->attributes()->custom_field3;
        
        $this->succeed($response, [
            "custom_field1"=>$custom_field1,
            "custom_field2"=>$custom_field2,
            "custom_field3"=>$custom_field3
        ]);
    }

    public function setAbout(Request $request, Response $response, Kernel $kernel)
    {
        if(!$this->requireAdministrativeRights(...\func_get_args()))
            return $this->fail($response, "Invalid hash");
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            "txt" => "required"
        ]);
        if($validation->fails()) {
            return $this->fail($response, "About txt is required");
        }
        $about = (string) $data["txt"];
        $graph = $kernel->graph();
        if(!$graph instanceof \PhoNetworksAutogenerated\Network) {
            return $this->fail($response, "Invalid function");
        }
        $kernel->graph()->setAbout($about);
        $kernel->graph()->persist();
        $this->succeed($response);
    }

    public function getAbout(Request $request, Response $response, Kernel $kernel)
    {
        $graph = $kernel->graph();
        if(!$graph instanceof \PhoNetworksAutogenerated\Network) {
            return $this->fail($response, "Invalid function");
        }
        $about = $kernel->graph()->getAbout();
        $this->succeed($response, [
            "about" => $about
        ]);
    }

    public function setCommentModeration(Request $request, Response $response, Kernel $kernel)
    {
        if(!$this->requireAdministrativeRights(...\func_get_args()))
            return $this->fail($response, "Invalid hash");
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            "moderated" => "required"
        ]);
        //$v->rule('boolean', ['moderated']);
        if($validation->fails()) {
            return $this->fail($response, "A boolean 'moderated' field is required");
        }
        $is_moderated = (bool) $data["moderated"];
        if(!$is_moderated) {
            $pending_comments = $this->_getPendingComments($kernel);
            foreach($pending_comments as $c) {
                try {
                    $comment = $kernel->gs()->edge($c["comment_id"]);
                    $comment->setPending(false);
                }
                catch (\Exception $e) {
                    error_log("a-oh can't fetch comment id ".$c["comment_id"]);
                }
            }
        }
        $kernel->graph()->setCommentsModerated($is_moderated);
        $kernel->graph()->persist();
        $this->succeed($response);
    }

    public function getCommentModeration(Request $request, Response $response, Kernel $kernel)
    {
        if(!$this->requireAdministrativeRights(...\func_get_args()))
            return $this->fail($response, "Invalid hash");
        $is_moderated = (bool) $kernel->graph()->getCommentsModerated();
        $this->succeed($response, ["is_moderated"=>$is_moderated]);
    }

    public function disapprovePendingComment(Request $request, Response $response,Kernel $kernel)
    {
        if(!$this->requireAdministrativeRights(...\func_get_args()))
            return $this->fail($response, "Invalid hash");
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            "comment_id" => "required"
        ]);
        if($validation->fails()) {
            $this->fail($response, "comment_id required");
            return;
        }
        try {
            $comment = $kernel->gs()->edge($data["comment_id"]);
        }
        catch(\Exception $e) {
            return $this->fail($response, "Invalid Comment ID.");
        }
        if(!$comment instanceof Comment)
            return $this->fail($response, "Invalid Comment.");
        $comment->destroy();
        $this->succeed($response);
    }

    public function fetchCounts(Request $request, Response $response, Kernel $kernel)
    {
        $nodes_count = $edges_count = $actors_count = 0;
        $entities = $kernel->database()->keys(); // because it's faster than ->members()
        foreach($entities as $entity) {
            $clue = \hexdec($entity[0]);
            if($clue==4) $actors_count++;
            if($clue < 6) $nodes_count++;
            else $edges_count++;
        }
        $this->succeed($response, [
            "actor_count" => (string) $actors_count,
            "node_count"  => (string) $nodes_count,
            "edge_count"  => (string) $edges_count
        ]);
    }

    public function setFounderPassword(Request $request, Response $response, Kernel $kernel)
    {
        if(!$this->requireAdministrativeRights(...\func_get_args()))
            return $this->fail($response, "Invalid hash");
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            "password" => "required"
        ]);
        if($validation->fails()) {
            $this->fail($response, "password required");
            return;
        }
        if(!$this->checkPasswordFormat($data["password"])) {
            $this->fail($response, "password format not good");
            return;
        }
        $founder = $kernel->founder();
        $founder->setPassword($data["password"]);
        $founder->persist();
        $this->succeed($response);
    }
 
         public function deleteMember(Request $request, Response $response, Kernel $kernel)
      {
        if(!$this->requireAdministrativeRights(...\func_get_args())) {
            return $this->fail($response, "Invalid hash");
        }
        $data = $request->getQueryParams();
        $validation = $this->validator->validate($data, [
            "id" => "required"
        ]);
        if($validation->fails()) {
            return $this->fail($response, "User ID unavailable.");
        }
        try {
            $entity = $kernel->gs()->entity($data["id"]);
        }
        catch(\Exception $e) {
            return $this->fail($response, "No such Entity");
        }
        if($entity instanceof User) {
            try {
                $entity->destroy();
            }
            catch(\Exception $e) {
                return $this->fail($response, "Problem with deleting the User");
            }
            return $this->succeed($response, [
                    "deleted" => $deleted
            ]);
        }
        $this->fail($response, "The ID does not belong to a User.");
    }
 
    public function fetchId(Request $request, Response $response, Kernel $kernel)
    {
      $data = $request->getQueryParams();
      $validation = $this->validator->validate($data, [
          "ref" => "required"
      ]);
      if($validation->fails()) {
          return $this->fail($response, "User ID unavailable.");
      }
      $clean_ref = \str_replace(['"',"'","\\"], "", $data["ref"]);
      $res = $kernel->index()->query("MATCH (a:user {Username: \"".$clean_ref."\"}) RETURN a.udid AS author_id");
        $array = $res->results();
        if(count($array)==1)
            return $this->succeed($response, ["id"=>$array[0]["author_id"]]);
        $res = $kernel->index()->query("MATCH (a:group {Title: \"".$clean_ref."\"}) RETURN a.udid AS group_id");
            $array = $res->results();
            if(count($array)==1)
                return $this->succeed($response, ["id"=>$array[0]["group_id"]]);
        $res = $kernel->index()->query("MATCH (a:group {Title: \"".\str_replace("_"," ", $clean_ref)."\"}) RETURN a.udid AS group_id");
                $array = $res->results();
                if(count($array)==1)
                    return $this->succeed($response, ["id"=>$array[0]["group_id"]]);

        return $this->fail($response, "No such user or group");
            
  }

  public function fetchSingleSignonToken(Request $request, Response $response, Kernel $kernel)
    {
        if(!$this->requireAdministrativeRights(...\func_get_args())) {
            return $this->fail($response, "Invalid hash");
        }
        $key = getenv("SINGLE_SIGNON_TOKEN_KEY") ? getenv("SINGLE_SIGNON_TOKEN_KEY") : "";
        if(empty($key)) {
            return $this->fail($response, "No single sign-on key");
        }
        return $this->succeed($response, ["key"=>$key]);
    }

}
