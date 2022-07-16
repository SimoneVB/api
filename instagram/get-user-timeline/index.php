<?php
/*
  instagram/get-user-timeline
  https://api2.simonevb.repl.co/instagram/get-user-timeline/?username=username
**/
  include '../../_includes/functions.php';
  $fun = new functions();
  allowCors();

  // gets username from query-string parameters
  $username = trim($fun->getParam('username'));

  // Calls IG API
  $base_url='https://i.instagram.com/api/v1/';
  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => "${base_url}users/web_profile_info/?username=${username}",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
        'user-agent: Mozilla/5.0 (iPhone; CPU iPhone OS 12_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Instagram 105.0.0.11.118 (iPhone11,8; iOS 12_3_1; en_US; en-US; scale=2.00; 828x1792; 165586599)'
      )
  ));
  $response = curl_exec($curl);
  curl_close($curl);

  if ($response != '') {
    if ($fun->isJson($response)) {
      $result = json_decode($response);
      $user = $result->data->user;
      $arrMedia = $user->edge_owner_to_timeline_media->edges;

      $userObj = new stdClass();
      $userObj->id = $user->id;
      $userObj->username = $user->username;
      $userObj->fullname = $user->full_name;
      $userObj->biography = $user->biography_with_entities->raw_text;
      $userObj->$followedBy = $user->edge_followed_by->count;
      $userObj->follow = $user->edge_follow->count;
      $userObj->isPrivate = $user->is_private;
      $userObj->isVerified = $user->is_verified;
      $userObj->categoryName = $user->business_category_name;
      $userObj->categoryEnum = $user->category_enum;
      $userObj->profilePicture = $fun->imageUrlToBase64($user->profile_pic_url_hd);  // profile pic HD to base64
      $userObj->mediaCount = $user->edge_owner_to_timeline_media->count;

      $arrMediaObj = [];
      foreach ($arrMedia as &$media) {
        $node = $media->node;

        $mediaObj = new stdClass();
        $mediaObj->id = $node->id;
        $mediaObj->shortcode = $node->shortcode;
        $mediaObj->url = 'https://www.instagram.com/p/' . $node->shortcode . '/';
        $mediaObj->width = $node->dimensions->width;
        $mediaObj->height = $node->dimensions->height;
        $mediaObj->isVideo = $node->is_video;
        $mediaObj->preview = $fun->imageUrlToBase64($node->display_url);
        $mediaObj->text = $node->edge_media_to_caption->edges[0]->node->text;
        $mediaObj->comments = $node->edge_media_to_comment->count;
        $mediaObj->likes = $node->edge_liked_by->count;
        
        array_push($arrMediaObj, $mediaObj);
      }
    
      $retObj = new stdClass();
      $retObj->user = $userObj;
      $retObj->media = $arrMediaObj;

      $fun->sendJSONResponse($retObj);
    } else {
      // API response malformed: not a JSON, but HTML: this means user not found
      $fun->sendError(404, 'user not found', true);
    }
  } else {
    // API empty response
  }
?>