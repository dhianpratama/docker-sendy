<?php include('../_connect.php');?>
<?php include('../../includes/helpers/short.php');?>
<?php

    function array_map_callback($a)
    {
      global $mysqli;

      return mysqli_real_escape_string($mysqli, $a);
    }

	//-------------------------- ERRORS -------------------------//
	$error_core = array('No data passed', 'API key not passed', 'Invalid API key');
	$error_passed = array('From name not passed', 'From email not passed', 'Reply to email not passed', 'Subject not passed', 'HTML not passed', 'List ID(s) not passed', 'One or more list IDs are invalid', 'List IDs does not belong to a single brand', 'Brand ID not passed.', 'Campaign does not exist.');
	//-----------------------------------------------------------//
	
	//--------------------------- POST --------------------------//
	//api_key	
	$api_key = isset($_POST['api_key']) ? mysqli_real_escape_string($mysqli, $_POST['api_key']) : null;

    //custom_fields
    $custom_fields = isset($_POST['custom_fields']) ? array_map('array_map_callback', $_POST['custom_fields']) : null;

	//parent_campaign
    $parent_campaign = isset($_POST['parent_campaign']) ? mysqli_real_escape_string($mysqli, $_POST['parent_campaign']) : null;

	//from_name
	$from_name = isset($_POST['from_name']) ? mysqli_real_escape_string($mysqli, $_POST['from_name']) : null;
	
	//from_email
	$from_email = isset($_POST['from_email']) ? mysqli_real_escape_string($mysqli, $_POST['from_email']) : null;
	
	//reply_to
	$reply_to = isset($_POST['reply_to']) ? mysqli_real_escape_string($mysqli, $_POST['reply_to']) : null;
	
	//title
	$title = isset($_POST['title']) ? mysqli_real_escape_string($mysqli, $_POST['title']) : null;
	
	//subject
	$subject = isset($_POST['subject']) ? mysqli_real_escape_string($mysqli, $_POST['subject']) : null;
	
	//plain_text
	$plain_text = isset($_POST['plain_text']) ? mysqli_real_escape_string($mysqli, $_POST['plain_text']) : null;
	
	//html_text
	$html_text = isset($_POST['html_text']) ? mysqli_real_escape_string($mysqli, $_POST['html_text']) : null;
	
	//list_ids (comma separated)
	$list_ids = isset($_POST['list_ids']) ? mysqli_real_escape_string($mysqli, $_POST['list_ids']) : null;
	
	//send_campaign (1 or 0)
	$send_campaign = isset($_POST['send_campaign']) ? mysqli_real_escape_string($mysqli, $_POST['send_campaign']) : 0;
	
	//brand_id (requierd if send_campaign is set to 0)
	$app = isset($_POST['brand_id']) ? mysqli_real_escape_string($mysqli, $_POST['brand_id']) : null;
	
	//query_string
	$query_string = isset($_POST['query_string']) ? mysqli_real_escape_string($mysqli, $_POST['query_string']) : null;
	
	//query_string
	$json = isset($_POST['json']) ? mysqli_real_escape_string($mysqli, $_POST['json']) : 0;
	//-----------------------------------------------------------//
	
	//----------------------- VERIFICATION ----------------------//
	//Core data
	if($api_key==null && $from_name==null && $from_email==null && $reply_to==null && $subject==null && $plain_text==null && $html_text==null && $list_ids==null)
	{
		echo $error_core[0];
		exit;
	}
	if($api_key==null)
	{
		echo $error_core[1];
		exit;
	}
	else if(!verify_api_key($api_key))
	{
		echo $error_core[2];
		exit;
	}
	
	//Passed data
	if($parent_campaign==null) {
	    if($from_name==null)
        	{
        		echo $error_passed[0];
        		exit;
        	}
        	else if($from_email==null)
        	{
        		echo $error_passed[1];
        		exit;
        	}
        	else if($reply_to==null)
        	{
        		echo $error_passed[2];
        		exit;
        	}
        	else if($subject==null)
        	{
        		echo $error_passed[3];
        		exit;
        	}
        	else if($html_text==null)
        	{
        		echo $error_passed[4];
        		exit;
        	}
	}
	else {
        //Check if the parent campaign passed into the API exists, else throw error
        $listid = trim(short($listid,true));
        $q = 'SELECT * FROM campaigns WHERE id = '.$parent_campaign;
        $r = mysqli_query($mysqli, $q);
        if (mysqli_num_rows($r) == 0)
        {
            echo $error_passed[9];
            exit;
        }
        else {
            $campaign = mysqli_fetch_array($r);
            //from_name
            $from_name = mysqli_real_escape_string($mysqli,$campaign['from_name']);
            $from_email = mysqli_real_escape_string($mysqli,$campaign['from_email']);
            $reply_to = mysqli_real_escape_string($mysqli,$campaign['reply_to']);
            $title = mysqli_real_escape_string($mysqli,$campaign['label']);
            $subject = mysqli_real_escape_string($mysqli,$campaign['title']);
            $plain_text = mysqli_real_escape_string($mysqli,$campaign['plain_text']);
            $html_text = mysqli_real_escape_string($mysqli,$campaign['html_text']);
            $query_string = mysqli_real_escape_string($mysqli,$campaign['query_string']);
            $app = mysqli_real_escape_string($mysqli,$campaign['app']);
        }
	}

	if($send_campaign && $list_ids==null)
	{
		echo $error_passed[5];
		exit;
	}
	else if(!$send_campaign && $app==null)
	{
		echo $error_passed[8];
		exit;
	}
	else
	{
		if($send_campaign)
		{
			//Check if all lists passed into the API exists, else throw error
			$list_id = explode(',', $list_ids);
			$brand_id_array = array();
			
			foreach($list_id as $listid)
			{
				$listid = trim(short($listid,true));
				$q = 'SELECT app FROM lists WHERE id = '.$listid;
				$r = mysqli_query($mysqli, $q);
				if (mysqli_num_rows($r) == 0) 
				{
					echo $error_passed[6]; 
					exit;
				}
				else while($row = mysqli_fetch_array($r)) array_push($brand_id_array, $row['app']);
			}
			
			//Check if all list IDs belong to the same brand, else throw error
			if(count(array_unique($brand_id_array)) != 1)
			{
				echo $error_passed[7];
				exit;
			}
			else
			{
				$app = $brand_id_array[0];
			}
		}
	}
	//-----------------------------------------------------------//
	
	//-------------------------- QUERY --------------------------//
	
	//Get userID
	$q = 'SELECT id FROM login ORDER BY id ASC LIMIT 1';
	$r = mysqli_query($mysqli, $q);
	if ($r) while($row = mysqli_fetch_array($r)) $userID = $row['id'];

	$custom_fields_string = "";
	if($custom_fields) {
        if(is_array($custom_fields)) {
            $custom_fields_string = implode("%s%", $custom_fields);
        }
        else {
            $custom_fields_string = $custom_fields;
        }
	}
	error_log($custom_fields_string);

	if($send_campaign)
	{
	    error_log("send campaign");

		//Set send time
		$sent = time();
		
		//Get list IDs
		foreach($list_id as $listid)
		{
			$listids .= trim(short($listid,true)).',';
		}
		$listids = substr($listids, 0, -1);
		
		//Get number of recipients to send to
		$q = 'SELECT id as to_send FROM subscribers WHERE list IN ('.$listids.') AND unsubscribed=0 AND bounced=0 AND complaint=0 AND confirmed=1 GROUP BY email';
		$r = mysqli_query($mysqli, $q);
		$to_send = mysqli_num_rows(mysqli_query($mysqli, $q));
		
		//Create and send campaign
		$q2 = 'INSERT INTO campaigns (userID, app, from_name, from_email, reply_to, title, label, plain_text, html_text, wysiwyg, sent, to_send, send_date, lists, timezone, query_string, custom_fields) VALUES ('.$userID.', '.$app.', "'.$from_name.'", "'.$from_email.'", "'.$reply_to.'", "'.$subject.'", "'.$title.'", "'.$plain_text.'", "'.$html_text.'", 1, "'.$sent.'", '.$to_send.', 0, "'.$listids.'", 0, "'.$query_string.'" , "'.$custom_fields_string.'")';
        error_log($q2);


		$r2 = mysqli_query($mysqli, $q2);
		if ($r2) 
		{
		        error_log("created");

			echo 'Campaign created and now sending';
			
			//Check if monthly quota needs to be updated
			$q = 'SELECT allocated_quota, current_quota FROM apps WHERE id = '.$app;
			$r = mysqli_query($mysqli, $q);
			if($r) 
			{
				while($row = mysqli_fetch_array($r)) 
				{
					$allocated_quota = $row['allocated_quota'];
					$current_quota = $row['current_quota'];
					$updated_quota = $current_quota + $to_send;
				}
			}
			//Update quota if a monthly limit was set
			if($allocated_quota!=-1)
			{
				//if so, update quota
				$q = 'UPDATE apps SET current_quota = '.$updated_quota.' WHERE id = '.$app;
				mysqli_query($mysqli, $q);
			}
		}
		else echo 'Unable to create and send campaign';
		exit;
	}
	else
	{
		//Create draft
		$q2 = 'INSERT INTO campaigns (userID, app, from_name, from_email, reply_to, title, label, plain_text, html_text, wysiwyg, query_string,custom_fields) VALUES ('.$userID.', '.$app.', "'.$from_name.'", "'.$from_email.'", "'.$reply_to.'", "'.$subject.'", "'.$title.'", "'.$plain_text.'", "'.$html_text.'", 1, "'.$query_string.'", "'.$custom_fields_string.'")';
		$r2 = mysqli_query($mysqli, $q2);
		if ($r2) 
		{
			//if asked for JSON response
			if($json)
			{
				$campaign_id = mysqli_insert_id($mysqli);
				echo '{"status":"Campaign created", "campaign_id": "'.$campaign_id.'"}';
			}
			//Otherwise return plain text response by default
			else echo 'Campaign created';
		}
		else echo 'Unable to create campaign';
		exit;
	}
	//-----------------------------------------------------------//
?>