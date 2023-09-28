<?php 
/* 
This script was created by Wa!id (Github: WalsGit) to migrate PHPBB polls to Flarum's fof/polls extension (https://discuss.flarum.org/d/20586-friendsofflarum-polls)

This script was tested with:
* Flarum version 1.8.2
* Imported polls from PHPBB version 3.3.x (core phpbb polls features, no special poll mods or extensions).
* Phpbb import made with Nitro Porter version 3.3 https://nitroporter.org
* MySQL database version 8.0.34-0
* PHP version 8.1.23 

IMPORTANT STEPS TO DO OR CHECK BEFORE RUNNING THIS SCRIPT
->READ everything and don't forget to check and modify all the const values BEFORE running this script. 

First, set the following parameters with you server config details */
const DB_HOST = "localhost"; // your mysql server
const DB_NAME = ""; // your mysql database name
const DB_USER = ""; // your mysql username
const DB_PASSWORD = ""; // your mysql password
const DB_CHARSET = "utf8mb4"; // prefered charset

// DB connexion
try {
    $dsn = "mysql:host=".DB_HOST.
       ";dbname=".DB_NAME.
       ";charset=".DB_CHARSET; 
    $db = new PDO($dsn, DB_USER, DB_PASSWORD);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo 'ERROR: ' . $e->getMessage();
}

/*
===== STEP 0.
Complete the phpbb migration (including polls) with NITRO PORTER info and after import check that you have these 3 tables in your flarum database: PREFIX_polls, PREFIX_poll_options & PREFIX_poll_votes (where PREFIX_, if used, will most likely be FLA_). Set their names in the following constants, plus those of your Flarum settings and discussions tables: */

const TABLE_POLLS = "FLA_polls"; // Name of the polls table created by the fof/polls extension activation (should probably be FLA_polls)
const TABLE_POLL_OPTIONS = "FLA_poll_options"; // Name of the poll_options
const TABLE_POLL_VOTES = "FLA_poll_votes"; // Name of the poll_votes table
const TABLE_FLA_SETTINGS = "FLA_settings"; // Name of your Flarum settings table (should be FLA_setting)
const TABLE_DISCUSSIONS = "FLA_discussions"; // Name of your Flarum discussions table

/*
===== STEP 1.
Rename those 3 tables (I suggest you add a 2 at the end of each one's name, so for example FLA_polls will become FLA_polls2) and report ther new names here: */
const TABLE_POLLS_RENAMED = "FLA_polls2"; // The renamed phpbb imported FLA_polls table
const TABLE_POLL_OPTIONS_RENAMED = "FLA_poll_options2"; // The renamed phpbb imported FLA_poll_options table
const TABLE_POLL_VOTES_RENAMED = "FLA_poll_votes2"; // The renamed phpbb imported FLA_poll_votes table */

/*
===== STEP 2.
Install the fof/polls extension; info : https://discuss.flarum.org/d/20586-friendsofflarum-polls (tested with version 2.1.1) and ACTIVATE it. (if activation doesn't work, it's probably because you didn't rename all 3 imported tables on the previous step). Check your database and if you renamed the old tables like I did (adding a 2 at the end) you should have after the activation these 6 tables : FLA_polls, FLA_polls2, FLA_poll_options, FLA_poll_options2, FLA_poll_votes and FLA_poll_votes2.

Proceed ONLY if that's the case and all is good (fof/polls is installed, activated and you have all those 6 tables in your DB)

===== STEP 3.
Note that during the nitro porter migration (v3.3) some phpbb poll settings will be lost (not migrated); also note that some of them are set per poll in PHPBB but as a general setting for all polls in fof/polls. That being said, the main missing settings are the poll_vote_change & poll_max_options columns found in the phpbb topics table.

fof/polls stores some poll specific settings as a json in the settings column of the FLA_polls table, so we'll need to set some "default" settings for all imported polls in the varibale below (after a little explainer of each field) :
>max_votes: [0 or +] Set it to 0 (zero) if you want users to be able to chose all the options. Suggested: 0 (to not risk breaking old polls with multiple choices, you can later manually change it by editing each poll on Flarum).
>hide_votes: [true or false] hide votes untill polling ends. Suggested: false.
>public_poll: [true or false] allow users to see who voted for what. Suggested: true.
>allow_change_vote: [true or false] allow users to change their vote. Suggested: true
>allow_multiple_votes: [true or false] allow users to picks 2 or more options. Suggested: true (again, to not risk breaking old polls with multiple choices).
    */
$pollSettings = '{"max_votes": 0, "hide_votes": false, "public_poll": true, "allow_change_vote": true, "allow_multiple_votes": true}';

// The following setting is applyied to all polls in fof/polls (it was on a per poll basis in phpbb). NOT TO BE CONFUSED with the previous phpbb setting poll_max_options.
const POLL_MAX_OPTIONS = 10; // I suggest you set it to the hightest number of options you had on any of your old phpbb polls in order to not break anything (if the most options you have in one of your old polls from phpbb is 14 possibles answers, then set it to 14. The fof/polls default value is 10.

/*
===== STEP 4.
UPLOAD this file to your flarum public folder and RUN it in your browser.(URL should be like https://yourflarumsite.tld/phpbb_polls_migration.php if you didn't rename this file of course)

===== STEP 5.
Polls table migration: FLA_polls2 to FLA_polls
*/
echo '<br>====================== [START POLL SETTINGS MIGRATION 1/4] ======================<br>';
// First we need to temporarily alter the FLA_polls table to add the old discussion_id column that will be needed later (step 6 2/4)
try {
    $stmt = $db->prepare('ALTER TABLE ' . TABLE_POLLS . ' ADD `discussion_id` INT UNSIGNED NULL DEFAULT NULL');
    $stmt->execute();
} catch(PDOException $e) {
    echo '<strong>ERROR</strong>: ' . $e->getMessage();
    die();
}

try {
    $stmt = $db->prepare('SELECT * FROM ' . TABLE_POLLS_RENAMED);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        // Get the first post ID of the discussion
        $stmt2 = $db->prepare('SELECT first_post_id FROM ' . TABLE_DISCUSSIONS . ' WHERE id = :id');
        $stmt2->bindParam(':id', $row["discussion_id"], PDO::PARAM_INT);
        $stmt2-> execute();
        $post_id = $stmt2->fetchColumn();
        
        $insert = $db->prepare('INSERT INTO ' . TABLE_POLLS . '(question, post_id, user_id, public_poll, end_date, created_at, updated_at, settings, discussion_id)
                                VALUES(:question, :post_id, :user_id, :public_poll, :end_date, :created_at, :updated_at, :settings, :discussion_id)');
        $insert->bindParam(':question', $row["question"], PDO::PARAM_STR);
        $insert->bindParam(':post_id', $post_id, PDO::PARAM_INT);
        $insert->bindParam(':user_id', $row["user_id"], PDO::PARAM_INT);
        $insert->bindParam(':public_poll', $row["public_poll"], PDO::PARAM_INT);
        $insert->bindParam(':end_date', $row["end_date"], PDO::PARAM_STR);
        $insert->bindParam(':created_at', $row["created_at"], PDO::PARAM_STR);
        $insert->bindParam(':updated_at', $row["updated_at"], PDO::PARAM_STR);
        $insert->bindParam(':settings', $pollSettings, PDO::PARAM_STR);
        $insert->bindParam(':discussion_id', $row["discussion_id"], PDO::PARAM_INT);

        $insert->execute();
        echo 'POLL [' . $row["discussion_id"] . '] ' . $row["questions"] . ' [SETTINGS MIGRATED]<br>';
    }
} catch(PDOException $e) {
    echo '<strong>ERROR</strong>: ' . $e->getMessage();
    die();
}

echo '************************** [END POLLS SETTINGS MIGRATION] **************************<br>';


/*
===== STEP 6.
Poll options' table migration: FLA_poll_options2 to FLA_poll_options
*/
echo '<br>====================== [START POLL OPTIONS MIGRATION 2/4] ======================<br>';

// We need to temporarily alter the FLA_poll_options table to add the old options id column that will be needed later
try {
    $stmt = $db->prepare('ALTER TABLE ' . TABLE_POLL_OPTIONS . ' ADD `old_option_id` INT UNSIGNED NULL DEFAULT NULL');
    $stmt->execute();
} catch(PDOException $e) {
    echo '<strong>ERROR</strong>: ' . $e->getMessage();
    die();
}

// Proceed with the migration
try {
    $stmt = $db->prepare('SELECT * FROM ' . TABLE_POLL_OPTIONS_RENAMED);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        // Get the new poll ID 
        $stmt2 = $db->prepare('SELECT id FROM ' . TABLE_POLLS . ' WHERE discussion_id = :discussion_id');
        $stmt2->bindParam(':discussion_id', $row["poll_id"], PDO::PARAM_INT);
        $stmt2-> execute();
        $poll_id = $stmt2->fetchColumn();

        $insert = $db->prepare('INSERT INTO ' . TABLE_POLL_OPTIONS .' (answer, poll_id, created_at, updated_at, vote_count, old_option_id)
                                VALUES(:answer, :poll_id, :created_at, :updated_at, :vote_count, :old_option_id)');
        $insert->bindParam(':answer', $row["answer"], PDO::PARAM_STR);
        $insert->bindParam(':poll_id', $poll_id, PDO::PARAM_INT);
        $insert->bindParam(':created_at', $row["created_at"], PDO::PARAM_STR);
        $insert->bindParam(':updated_at', $row["updated_at"], PDO::PARAM_STR);
        $insert->bindParam(':vote_count', $row["vote_count"], PDO::PARAM_INT);
        $insert->bindParam(':old_option_id', $row["id"], PDO::PARAM_INT);

        $insert->execute();
        echo 'POLL [' . $poll_id .'] ANSWER [' . $row["id"] . '] ' . $row["answer"] . ' [OPTION MIGRATED]<br>';
        
    }
} catch(PDOException $e) {
    echo '<strong>ERROR</strong>: ' . $e->getMessage();
    die();
}

echo '************************** [END POLL OPTIONS MIGRATION] **************************<br>';

/*
===== STEP 7.
Poll votes' table migration: FLA_poll_votes2 to FLA_poll_votes
*/
echo '<br>====================== [START POLL VOTES MIGRATION 3/4] ======================<br>';
try {
    $stmt = $db->prepare('SELECT * FROM '. TABLE_POLL_VOTES_RENAMED);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      
        // Get the new options ID
        $stmt2 = $db->prepare('SELECT id FROM ' . TABLE_POLL_OPTIONS . ' WHERE old_option_id = :old_option_id');
        $stmt2->bindParam(':old_option_id', $row["option_id"], PDO::PARAM_INT);
        $stmt2-> execute();
        $option_id = $stmt2->fetchColumn();
        
        // Get the new poll ID 
        $stmt3 = $db->prepare('SELECT id FROM ' . TABLE_POLLS . ' WHERE discussion_id = :discussion_id');
        $stmt3->bindParam(':discussion_id', $row["poll_id"], PDO::PARAM_INT);
        $stmt3-> execute();
        $poll_id = $stmt3->fetchColumn();

        $insert = $db->prepare('INSERT INTO ' . TABLE_POLL_VOTES . ' (poll_id, option_id, user_id, created_at, updated_at)
                                VALUES(:poll_id, :option_id, :user_id, :created_at, :updated_at)');
        $insert->bindParam(':poll_id', $poll_id, PDO::PARAM_INT);
        $insert->bindParam(':option_id', $option_id, PDO::PARAM_INT);
        $insert->bindParam(':user_id', $row["user_id"], PDO::PARAM_INT);
        $insert->bindParam(':created_at', $row["created_at"], PDO::PARAM_STR);
        $insert->bindParam(':updated_at', $row["updated_at"], PDO::PARAM_STR);

        $insert->execute();
        echo 'POLL [' . $row["poll_id"] .'] VOTE [' . $option_id . '] BY USER #' . $row["userid"] . ' [VOTE MIGRATED]<br>';
        
    }
} catch(PDOException $e) {
    echo '<strong>ERROR</strong>: ' . $e->getMessage();
    die();
}

echo '************************** [END POLL VOTES MIGRATION] **************************<br>';

/*
===== STEP 8.
Calculating and updating the vote count of each poll and cleanup (remove temporary column old_option_id)
*/
echo '<br>====================== [UPDATING TOTAL VOTE COUNTS & CLEANUP 4/4] ======================<br>';
try {
    $stmt = $db->prepare('SELECT id FROM ' . TABLE_POLLS);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        // Get the vote count
        $stmt2 = $db->prepare('SELECT count(*) FROM ' . TABLE_POLL_VOTES . ' WHERE poll_id = :poll_id GROUP BY poll_id');
        $stmt2->bindParam(':poll_id', $row["id"], PDO::PARAM_INT);
        $stmt2-> execute();
        $vote_count = $stmt2->fetchColumn();

        $insert = $db->prepare('UPDATE ' . TABLE_POLLS . ' SET vote_count = :vote_count WHERE id = :id');
        $insert->bindParam(':vote_count', $vote_count, PDO::PARAM_INT);
        $insert->bindParam(':id', $row["id"], PDO::PARAM_INT);
        $insert->execute();
        echo 'POLL [' . $row["id"] .'] TOTAL VOTES [' . $vote_count . '] [VOTE COUNT UPDATED]<br>';
    }
} catch(PDOException $e) {
    echo '<strong>ERROR</strong>: ' . $e->getMessage();
    die();
}

// Setting the POLL_MAX_OPTIONS
try {
    $stmt = $db->prepare('UPDATE ' . TABLE_FLA_SETTINGS . ' SET ' . TABLE_FLA_SETTINGS . '.value = :maxoptions WHERE ' . TABLE_FLA_SETTINGS . '.key = "fof-polls.maxOptions"');
    $value = POLL_MAX_OPTIONS;
    $stmt->bindParam(':maxoptions', $value, PDO::PARAM_INT);
    $stmt->execute();
    echo 'POLL MAX OPTIONS SET TO: ' . $value .'<br>';
} catch(PDOException $e) {
    echo '<strong>ERROR</strong>: ' . $e->getMessage();
    die();
}


// clean up
try {
    $stmt = $db->prepare('ALTER TABLE ' . TABLE_POLLS . ' DROP discussion_id');
    $stmt->execute();
    echo 'Columns discussion_id removed from the table ' . TABLE_POLLS .'<br>';
} catch(PDOException $e) {
    echo '<strong>ERROR</strong>: ' . $e->getMessage();
    die();
}

try {
    $stmt = $db->prepare('ALTER TABLE ' . TABLE_POLL_OPTIONS . ' DROP old_option_id');
    $stmt->execute();
    echo 'Columns old_option_id removed from the table ' . TABLE_POLL_OPTIONS .'<br>';
} catch(PDOException $e) {
    echo '<strong>ERROR</strong>: ' . $e->getMessage();
    die();
}

echo '************************** [END MIGRATION] **************************<br>';


echo '<br><br><strong>END OF MIGRATION, YOU CAN NOW DELETE THIS FILE FROM YOU SERVER<br> AND YOU CAN ALSO MANUALLY REMOVE FROM YOUR DB THE TABLES ' . TABLE_POLLS_RENAMED .', ' .TABLE_POLL_OPTIONS_RENAMED . ' & ' . TABLE_POLL_VOTES_RENAMED .'</strong>';
