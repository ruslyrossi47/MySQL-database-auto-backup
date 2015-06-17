<?php
ini_set('memory_limit','200M');

define('DB_HOSTNAME', '');
define('DB_USERNAME', '');
define('DB_PASSWORD', '');
define('DB_DATABASE', '');
define('BACKUP_FILENAME', 'database-backup');
define('BACKUP_FOLDER', 'database_backup/');
define('LOG_FOLDER', 'log/');
define('EMAIL_FROM', '');
define('EMAIL_TO', '');

date_default_timezone_set ("Asia/Kuala_Lumpur");

// remove previos sql file
$files = glob(BACKUP_FOLDER . '*.sql');
foreach ($files as $file) {
	unlink($file);
}

backup_tables(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);

// backup the db OR just a table
function backup_tables($host,$user,$pass,$name,$tables = '*') {
	
	$link = mysql_connect($host,$user,$pass);
	mysql_select_db($name,$link);
	
	// get all of the tables
	if($tables == '*')
	{
		$tables = array();
		$result = mysql_query('SHOW TABLES');
		while($row = mysql_fetch_row($result))
		{
			$tables[] = $row[0];
		}
	}
	else
	{
		$tables = is_array($tables) ? $tables : explode(',',$tables);
	}
	
	// cycle through
	foreach($tables as $table)
	{

		$result = mysql_query('SELECT * FROM '.$table);
		$num_fields = mysql_num_fields($result);
		
		$return.= 'DROP TABLE '.$table.';';
		$row2 = mysql_fetch_row(mysql_query('SHOW CREATE TABLE '.$table));
		$return.= "\n\n".$row2[1].";\n\n";
		
		for ($i = 0; $i < $num_fields; $i++) 
		{
			while($row = mysql_fetch_row($result))
			{
				$return.= 'INSERT INTO '.$table.' VALUES(';
				for($j=0; $j<$num_fields; $j++) 
				{
					$row[$j] = addslashes($row[$j]);
					$row[$j] = preg_replace("/\n/","\\n",$row[$j]);
					if (isset($row[$j])) { $return.= '"'.$row[$j].'"' ; } else { $return.= '""'; }
					if ($j<($num_fields-1)) { $return.= ','; }
				}
				$return.= ");\n";
			}
		}
		$return.="\n\n\n";
	}
	
	//save file
	$file_path = BACKUP_FOLDER . BACKUP_FILENAME . '-' . date('Y-m-d-h-i-A') . '.sql';

	if (!file_exists(BACKUP_FOLDER)) {
	    mkdir(BACKUP_FOLDER, 0777, true);
	}

	$handle = fopen($file_path,'w+');
	fwrite($handle,$return);
	fclose($handle);

	// get file size after download
	$file_size_in_server = formatSizeUnits(filesize($file_path));

	// log activity
	if (!file_exists(LOG_FOLDER)) {
	    mkdir(LOG_FOLDER, 0777, true);
	}

	$file = LOG_FOLDER . 'backup.log';

	// Open the file to get existing content
	$current = file_get_contents($file);
	$current .= date('Y-m-d h:i:s')."\t " . $file_size_in_server. " \n";

	// Write the contents back to the file
	file_put_contents($file, $current);

	require 'PHPMailer-master/class.phpmailer.php';

	$email = new PHPMailer();
	$email->From      = EMAIL_FROM;
	$email->FromName  =  $_SERVER['SERVER_NAME'] . ' - System';
	$email->Subject   =  $_SERVER['SERVER_NAME'] . ' - Database Backup: ' . date('Y-m-d');
	$email->Body      = 'This is an automatically generated email, please do not reply to this email address.';
	$email->addAddress(EMAIL_TO);

	$file_to_attach = BACKUP_FOLDER . BACKUP_FILENAME . '-' . date('Y-m-d-h-i-A') . '.sql';
	$email->addAttachment($file_to_attach);
	
	return $email->Send();
}

// convert byte into readable format
function formatSizeUnits($bytes) {

    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }

    return $bytes;
}
?>